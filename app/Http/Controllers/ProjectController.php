<?php

namespace App\Http\Controllers;

use App\Helpers\AuthUserHelper;
use App\Models\AcademicPeriod;
use App\Models\AcademicProcessWindow;
use App\Models\City;
use App\Models\CityProgram;
use App\Models\Content;
use App\Models\ContentVersion;
use App\Models\Framework;
use App\Models\InvestigationLine;
use App\Models\Professor;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Student;
use App\Models\ThematicArea;
use App\Models\User;
use App\Models\Version;
use App\Services\AcademicCalendar\AcademicCalendarService;
use App\Services\Projects\ProjectAgeReviewService;
use App\Services\Projects\TeacherIdeaBalanceService;
use App\Services\Students\StudentAcademicProgressService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    /**
     * Cache of content identifiers keyed by normalized name.
     *
     * @var array<string, int>
     */
    protected array $contentCache = [];

    /**
     * Cached identifier for the "waiting evaluation" status.
     */
    protected ?int $waitingStatusId = null;

    /**
     * Display a paginated list of projects for the authenticated user.
     */
    public function index(Request $request): View|StreamedResponse
    {
        $user = AuthUserHelper::fullUser();

        $query = Project::query()
            ->with([
                'thematicArea.investigationLine',
                'projectStatus',
                'professors' => static fn ($relation) => $relation
                    ->with(['user', 'cityProgram.program'])
                    ->orderBy('last_name')
                    ->orderBy('name'),
                'students' => static fn ($relation) => $relation
                    ->orderBy('last_name')
                    ->orderBy('name'),
            ])
            ->orderByDesc('created_at');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        $statusFilter = $request->input('status_id');
        if ($statusFilter) {
            $query->where('project_status_id', $statusFilter);
        }

        $pendingReviewDueToAge = $request->boolean('pending_review_due_to_age');
        if ($user?->role === 'research_staff' && $pendingReviewDueToAge) {
            $query->pendingReviewDueToAge();
        }

        $cityPrograms = CityProgram::with(['program', 'city'])->get();
        $selectedCityProgram = $request->integer('city_program_id');

        if ($user?->role === 'research_staff' && $selectedCityProgram) {
            $query->whereHas('professors', function ($q) use ($selectedCityProgram) {
                $q->where('city_program_id', $selectedCityProgram);
            });
        }

        if (in_array($user?->role, ['professor', 'committee_leader'], true) && $user->professor) {
            $professorId = $user->professor->id;

            $query->whereHas('professors', static function ($relation) use ($professorId) {
                $relation->where('professors.id', $professorId);
            });
        } elseif ($user?->role === 'student' && $user->student) {
            $studentId = $user->student->id;

            $query->whereHas('students', static function ($relation) use ($studentId) {
                $relation->where('students.id', $studentId);
            });
        }

        $projects = $query->paginate(10)->withQueryString();
        $reportState = $this->emptyProjectReportState();

        if ($user?->role === 'research_staff') {
            $reportState = $this->buildProjectReportState($request, $user);

            if ($reportState['export'] === 'csv') {
                return $this->streamProjectReportCsv(
                    $reportState['reportKey'],
                    $reportState['reportLabel'],
                    $reportState['reportData']
                );
            }
        }

        $projectAgeReviewService = app(ProjectAgeReviewService::class);
        $projects->getCollection()->transform(function (Project $project) use ($projectAgeReviewService) {
            $project->setAttribute('pending_review_due_to_age', $projectAgeReviewService->shouldFlag($project));
            $project->setAttribute('elapsed_periods_since_proposal', $projectAgeReviewService->elapsedAcademicPeriods($project));

            return $project;
        });

        $programCatalog = collect();
        if ($user?->role === 'committee_leader') {
            $programCatalog = Program::query()->orderBy('name')->get();
        }

        $projectStatuses = ProjectStatus::orderBy('name')->get();

        $proposalWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL);
        $proposalWindowOpen = $proposalWindow !== null;
        $proposalWindowMessage = $proposalWindowOpen
            ? null
            : AcademicCalendarService::processWindowUnavailableMessage(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL);
        $activeAcademicPeriod = AcademicCalendarService::currentActivePeriod();
        $studentAcademicProgress = app(StudentAcademicProgressService::class);

        $enableButtonStudent = true;

        if ($user?->role === 'student' && $user->student) {
            $enableButtonStudent = $studentAcademicProgress->canCreateProposal($user->student, $activeAcademicPeriod);
        }

        return view('projects.index', [
            'projects' => $projects,
            'search' => $search,
            'isProfessor' => in_array($user?->role, ['professor', 'committee_leader'], true),
            'isStudent' => $user?->role === 'student',
            'isCommitteeLeader' => $user?->role === 'committee_leader',
            'isResearchStaff' => $user?->role === 'research_staff',
            'programCatalog' => $programCatalog,
            'enableButtonStudent' => $enableButtonStudent,
            'projectStatuses' => $projectStatuses,
            'selectedStatus' => $statusFilter,
            'cityPrograms' => $cityPrograms,
            'selectedCityProgram' => $selectedCityProgram,
            'pendingReviewDueToAge' => $pendingReviewDueToAge,
            'proposalWindow' => $proposalWindow,
            'proposalWindowOpen' => $proposalWindowOpen,
            'proposalWindowMessage' => $proposalWindowMessage,
            'activeAcademicPeriod' => $activeAcademicPeriod,
            'canCreateProject' => (in_array($user?->role, ['professor', 'committee_leader'], true) || ($user?->role === 'student' && $enableButtonStudent)) && $proposalWindowOpen,
            'reportModules' => $this->projectReportModules(),
            'reportFilters' => $reportState['filters'],
            'reportData' => $reportState['reportData'],
            'reportSegments' => $reportState['segments'],
            'activeReportKey' => $reportState['reportKey'],
            'reportProgramOptions' => Program::query()
                ->selectRaw('MIN(id) as id, name')
                ->groupBy('name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Build the report state rendered below the projects list.
     *
     * @return array{
     *     filters: array{report_key:string,report_search:?string,report_from:?string,report_to:?string,report_program_id:?int},
     *     reportKey: string,
     *     reportLabel: string,
     *     reportData: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *     segments: array<int, array{label: string, value: int, percentage: float, color: string}>,
     *     export: ?string
     * }
     */
    protected function buildProjectReportState(Request $request, ?User $user): array
    {
        $filters = $request->validate([
            'report_key' => ['nullable', Rule::in(array_keys($this->projectReportModules()))],
            'report_search' => ['nullable', 'string', 'max:120'],
            'report_from' => ['nullable', 'date'],
            'report_to' => ['nullable', 'date', 'after_or_equal:report_from'],
            'report_program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'report_export' => ['nullable', Rule::in(['csv'])],
        ]);

        $reportKey = $filters['report_key'] ?? 'projects_by_status';
        $reportModules = $this->projectReportModules();
        $reportLabel = $reportModules[$reportKey]['label'] ?? $reportModules['projects_by_status']['label'];
        $normalizedFilters = [
            'report_key' => $reportKey,
            'report_search' => isset($filters['report_search']) ? trim((string) $filters['report_search']) : null,
            'report_from' => $filters['report_from'] ?? null,
            'report_to' => $filters['report_to'] ?? null,
            'report_program_id' => isset($filters['report_program_id']) ? (int) $filters['report_program_id'] : null,
        ];

        $reportData = $this->generateProjectDistributionReport($reportKey, $normalizedFilters, $user);

        return [
            'filters' => $normalizedFilters,
            'reportKey' => $reportKey,
            'reportLabel' => $reportLabel,
            'reportData' => $reportData,
            'segments' => $this->buildProjectReportSegments($reportData),
            'export' => $filters['report_export'] ?? null,
        ];
    }

    /**
     * @return array{
     *     filters: array{report_key:string,report_search:?string,report_from:?string,report_to:?string,report_program_id:?int},
     *     reportKey: string,
     *     reportLabel: string,
     *     reportData: array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int},
     *     segments: array<int, array{label: string, value: int, percentage: float, color: string}>,
     *     export: null
     * }
     */
    protected function emptyProjectReportState(): array
    {
        return [
            'filters' => [
                'report_key' => 'projects_by_status',
                'report_search' => null,
                'report_from' => null,
                'report_to' => null,
                'report_program_id' => null,
            ],
            'reportKey' => 'projects_by_status',
            'reportLabel' => 'Proyectos por estado',
            'reportData' => [
                'categories' => [],
                'values' => [],
                'percentages' => [],
                'total' => 0,
            ],
            'segments' => [],
            'export' => null,
        ];
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    protected function projectReportModules(): array
    {
        return [
            'projects_by_status' => [
                'label' => 'Proyectos por estado',
                'description' => 'Compara la distribucion de proyectos segun su estado actual.',
            ],
            'projects_by_author_type' => [
                'label' => 'Proyectos por tipo de autor',
                'description' => 'Clasifica cada proyecto segun si participan estudiantes, docentes o ambos tipos de autores.',
            ],
            'projects_by_thematic_area' => [
                'label' => 'Proyectos por area tematica',
                'description' => 'Muestra que areas tematicas concentran mayor cantidad de proyectos.',
            ],
            'projects_by_investigation_line' => [
                'label' => 'Proyectos por linea de investigacion',
                'description' => 'Permite comparar los proyectos agrupados por linea de investigacion.',
            ],
        ];
    }

    /**
     * @param  array{report_key:string,report_search:?string,report_from:?string,report_to:?string,report_program_id:?int}  $filters
     * @return array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int}
     */
    protected function generateProjectDistributionReport(string $reportKey, array $filters, ?User $user): array
    {
        $query = $this->baseProjectReportQuery($user);

        match ($reportKey) {
            'projects_by_author_type' => $this->applyAuthorTypeDistribution($query),
            'projects_by_thematic_area' => $this->applyThematicAreaDistribution($query),
            'projects_by_investigation_line' => $this->applyInvestigationLineDistribution($query),
            default => $this->applyStatusDistribution($query),
        };

        $this->applyProjectReportFilters($query, $filters, $reportKey);

        $rows = $query->get();
        $categories = [];
        $values = [];

        foreach ($rows as $row) {
            $categories[] = (string) $row->category;
            $values[] = (int) $row->total;
        }

        $total = array_sum($values);
        $percentages = array_map(
            static fn (int $value): float => $total > 0 ? round(($value / $total) * 100, 2) : 0.0,
            $values
        );

        return [
            'categories' => $categories,
            'values' => $values,
            'percentages' => $percentages,
            'total' => $total,
        ];
    }

    protected function baseProjectReportQuery(?User $user): Builder
    {
        $query = Project::query()->from('projects');

        if (in_array($user?->role, ['professor', 'committee_leader'], true) && $user?->professor) {
            $professorId = $user->professor->id;

            $query->whereHas('professors', static function (Builder $relation) use ($professorId) {
                $relation->where('professors.id', $professorId);
            });
        } elseif ($user?->role === 'student' && $user->student) {
            $studentId = $user->student->id;

            $query->whereHas('students', static function (Builder $relation) use ($studentId) {
                $relation->where('students.id', $studentId);
            });
        }

        return $query;
    }

    protected function applyStatusDistribution(Builder $query): void
    {
        $query
            ->leftJoin('project_statuses', 'project_statuses.id', '=', 'projects.project_status_id')
            ->selectRaw("COALESCE(project_statuses.name, 'Sin estado') as category")
            ->selectRaw('COUNT(projects.id) as total')
            ->groupBy('category')
            ->orderByDesc('total');
    }

    protected function applyThematicAreaDistribution(Builder $query): void
    {
        $query
            ->leftJoin('thematic_areas', 'thematic_areas.id', '=', 'projects.thematic_area_id')
            ->selectRaw("COALESCE(thematic_areas.name, 'Sin area tematica') as category")
            ->selectRaw('COUNT(projects.id) as total')
            ->groupBy('category')
            ->orderByDesc('total');
    }

    protected function applyAuthorTypeDistribution(Builder $query): void
    {
        $categoryExpression = $this->projectAuthorTypeCategoryExpression();

        $query
            ->selectRaw("{$categoryExpression} as category")
            ->selectRaw('COUNT(projects.id) as total')
            ->groupBy('category')
            ->orderByRaw(
                "FIELD(category, 'Estudiante', 'Docente', 'Mixto', 'Sin autores')"
            );
    }

    protected function projectAuthorTypeCategoryExpression(): string
    {
        return <<<SQL
CASE
    WHEN EXISTS (
        SELECT 1
        FROM student_project
        WHERE student_project.project_id = projects.id
    ) AND EXISTS (
        SELECT 1
        FROM professor_project
        WHERE professor_project.project_id = projects.id
    ) THEN 'Mixto'
    WHEN EXISTS (
        SELECT 1
        FROM student_project
        WHERE student_project.project_id = projects.id
    ) THEN 'Estudiante'
    WHEN EXISTS (
        SELECT 1
        FROM professor_project
        WHERE professor_project.project_id = projects.id
    ) THEN 'Docente'
    ELSE 'Sin autores'
END
SQL;
    }

    protected function applyInvestigationLineDistribution(Builder $query): void
    {
        $query
            ->leftJoin('thematic_areas', 'thematic_areas.id', '=', 'projects.thematic_area_id')
            ->leftJoin('investigation_lines', 'investigation_lines.id', '=', 'thematic_areas.investigation_line_id')
            ->selectRaw("COALESCE(investigation_lines.name, 'Sin linea de investigacion') as category")
            ->selectRaw('COUNT(projects.id) as total')
            ->groupBy('category')
            ->orderByDesc('total');
    }

    /**
     * @param  array{report_key:string,report_search:?string,report_from:?string,report_to:?string,report_program_id:?int}  $filters
     */
    protected function applyProjectReportFilters(Builder $query, array $filters, string $reportKey): void
    {
        if ($filters['report_program_id']) {
            $programId = $filters['report_program_id'];

            $query->where(function (Builder $builder) use ($programId): void {
                $builder
                    ->whereHas('professors.cityProgram', static function (Builder $relation) use ($programId): void {
                        $relation->where('program_id', $programId);
                    })
                    ->orWhereHas('students.cityProgram', static function (Builder $relation) use ($programId): void {
                        $relation->where('program_id', $programId);
                    });
            });
        }

        if ($filters['report_from']) {
            $from = $filters['report_from'];

            $query->where(static function (Builder $builder) use ($from): void {
                $builder
                    ->whereDate('projects.proposed_at', '>=', $from)
                    ->orWhere(static function (Builder $fallback) use ($from): void {
                        $fallback
                            ->whereNull('projects.proposed_at')
                            ->whereDate('projects.created_at', '>=', $from);
                    });
            });
        }

        if ($filters['report_to']) {
            $to = $filters['report_to'];

            $query->where(static function (Builder $builder) use ($to): void {
                $builder
                    ->whereDate('projects.proposed_at', '<=', $to)
                    ->orWhere(static function (Builder $fallback) use ($to): void {
                        $fallback
                            ->whereNull('projects.proposed_at')
                            ->whereDate('projects.created_at', '<=', $to);
                    });
            });
        }

        if ($filters['report_search']) {
            $term = '%' . $filters['report_search'] . '%';

            $query->where(function (Builder $builder) use ($term, $reportKey): void {
                $builder->where('projects.title', 'like', $term);

                match ($reportKey) {
                    'projects_by_author_type' => $builder->orWhereRaw($this->projectAuthorTypeCategoryExpression() . ' like ?', [$term]),
                    'projects_by_thematic_area' => $builder->orWhere('thematic_areas.name', 'like', $term),
                    'projects_by_investigation_line' => $builder
                        ->orWhere('thematic_areas.name', 'like', $term)
                        ->orWhere('investigation_lines.name', 'like', $term),
                    default => $builder->orWhere('project_statuses.name', 'like', $term),
                };
            });
        }
    }

    /**
     * @param  array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int}  $reportData
     * @return array<int, array{label: string, value: int, percentage: float, color: string}>
     */
    protected function buildProjectReportSegments(array $reportData): array
    {
        $palette = [
            '#0f766e',
            '#1d4ed8',
            '#b45309',
            '#be123c',
            '#7c3aed',
            '#0891b2',
            '#4d7c0f',
            '#c2410c',
        ];

        $segments = [];

        foreach ($reportData['categories'] as $index => $category) {
            $segments[] = [
                'label' => $category,
                'value' => $reportData['values'][$index] ?? 0,
                'percentage' => $reportData['percentages'][$index] ?? 0.0,
                'color' => $palette[$index % count($palette)],
            ];
        }

        return $segments;
    }

    /**
     * @param  array{categories: array<int, string>, values: array<int, int>, percentages: array<int, float>, total: int}  $reportData
     */
    protected function streamProjectReportCsv(string $reportKey, string $reportLabel, array $reportData): StreamedResponse
    {
        $filename = sprintf(
            'reporte-%s-%s.csv',
            str_replace('_', '-', $reportKey),
            now()->format('Ymd-His')
        );

        return response()->streamDownload(function () use ($reportLabel, $reportData): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [$reportLabel]);
            fputcsv($handle, ['Categoria', 'Valor', 'Porcentaje']);

            foreach ($reportData['categories'] as $index => $category) {
                fputcsv($handle, [
                    $category,
                    $reportData['values'][$index] ?? 0,
                    $reportData['percentages'][$index] ?? 0,
                ]);
            }

            fputcsv($handle, ['Total', $reportData['total'], 100]);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Ensure the current user is allowed to interact with the projects module.
     *
     * @return array{0:\App\Models\User|null,1:bool,2:bool,3:bool,4:bool}
     */
    protected function ensureRoleAccess(bool $allowResearchStaff = false): array
    {
        $user = AuthUserHelper::fullUser();
        $isProfessor = in_array($user?->role, ['professor', 'committee_leader'], true);
        $isStudent = $user?->role === 'student';
        $isResearchStaff = $user?->role === 'research_staff';
        $isCommitteeLeader = $user?->role === 'committee_leader';

        if (! $isProfessor && ! $isStudent && ! ($allowResearchStaff && $isResearchStaff)) {
            abort(403, 'This action is only available for professors, committee leaders or students.');
        }

        return [$user, $isProfessor, $isStudent, $isResearchStaff, $isCommitteeLeader];
    }

    /**
     * Show the form used to create a new project idea.
     */
    public function create(): View|RedirectResponse
    {
        [$user, $isProfessor, $isStudent, $isResearchStaff, $isCommitteeLeader] = $this->ensureRoleAccess(true);
        $activeProfessor = $this->resolveProfessorProfile($user);

        if ($isResearchStaff) {
            abort(403, 'Research staff members cannot create project ideas.');
        }

        if (! AcademicCalendarService::isProcessWindowOpen(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)) {
            return view(
                'academic-calendar.unavailable',
                AcademicCalendarService::unavailableActivityViewData(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)
            );
        }

        $proposalWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL);
        $activeAcademicPeriod = AcademicCalendarService::currentActivePeriod();

        if (! $proposalWindow || ! $activeAcademicPeriod) {
            return view(
                'academic-calendar.unavailable',
                AcademicCalendarService::unavailableActivityViewData(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)
            );
        }

        if ($isProfessor) {
            $researchGroupId = $activeProfessor?->cityProgram?->program?->research_group_id;
        } else {
            $student = $user->student;
            $studentAcademicProgress = app(StudentAcademicProgressService::class);

            if (! $studentAcademicProgress->canCreateProposal($student, $activeAcademicPeriod)) {
                abort(403, $studentAcademicProgress->blockedProposalMessage($student, $activeAcademicPeriod));
            }

            $researchGroupId = $student?->cityProgram?->program?->research_group_id;
        }

        $cities = City::query()->orderBy('name')->get();
        $programs = Program::query()->with('researchGroup')->orderBy('name')->get();
        $investigationLines = InvestigationLine::where('research_group_id', $researchGroupId)
            ->whereNull('deleted_at')
            ->get();
        $thematicAreas = ThematicArea::query()->orderBy('name')->get();

        $year = now()->year;

        $frameworks = Framework::with('contentFrameworks')
            ->where('start_year', '<=', $year)
            ->where('end_year', '>=', $year)
            ->orderBy('name')
            ->get();

        $prefill = [
            'delivery_date' => Carbon::now()->format('Y-m-d'),
        ];

        $availableStudents = collect();
        $availableProfessors = collect();
        $ideaBalanceRecommendations = null;

        if ($isProfessor) {
            $professor = $activeProfessor;
            if (! $professor) {
                abort(403, 'Professor profile required to submit proposals.');
            }

            $prefill = array_merge($prefill, [
                'first_name' => $professor->name,
                'last_name' => $professor->last_name,
                'email' => $professor->mail ?? $user->email,
                'phone' => $professor->phone,
                'city_id' => optional($professor->cityProgram)->city_id,
                'program_id' => optional($professor->cityProgram)->program_id,
            ]);

            $availableProfessors = $this->participantQuery($professor->id)
                ->get()
                ->map(fn (Professor $participant) => $this->presentParticipant($participant));

            $ideaBalanceRecommendations = app(TeacherIdeaBalanceService::class)->recommendationsForUser($user);
        } else {
            $student = $user->student;
            if (! $student) {
                abort(403, 'Student profile required to submit proposals.');
            }

            $cityProgram = $student->cityProgram;
            $program = $cityProgram?->program;
            $researchGroup = $program?->researchGroup;

            $prefill = array_merge($prefill, [
                'first_name' => $student->name,
                'last_name' => $student->last_name,
                'card_id' => $student->card_id,
                'email' => $user->email,
                'phone' => $student->phone,
                'city_id' => $cityProgram?->city_id,
                'program_id' => $program?->id,
                'research_group' => $researchGroup?->name,
            ]);

            $availableStudents = Student::query()
                ->where('city_program_id', $student->city_program_id)
                ->where('id', '!=', $student->id)
                ->where(function ($q) {
                    $q->whereDoesntHave('projects')
                        ->orWhere(function ($q2) {
                            $q2->whereHas('projects', fn ($p) => $p->whereHas('projectStatus', fn ($s) => $s->where('name', 'Rechazado')))
                                ->whereDoesntHave('projects', fn ($p) => $p->whereHas('projectStatus', fn ($s) => $s->whereNot('name', 'Rechazado')));
                        });
                })
                ->orderBy('last_name')
                ->orderBy('name')
                ->get();
        }

        return view('projects.create', [
            'cities' => $cities,
            'programs' => $programs,
            'investigationLines' => $investigationLines,
            'thematicAreas' => $thematicAreas,
            'frameworks' => $frameworks,
            'prefill' => $prefill,
            'isProfessor' => $isProfessor,
            'isStudent' => $isStudent,
            'isCommitteeLeader' => $isCommitteeLeader,
            'availableStudents' => $availableStudents,
            'availableProfessors' => $availableProfessors,
            'activeAcademicPeriod' => $activeAcademicPeriod,
            'proposalWindow' => $proposalWindow,
            'ideaBalanceRecommendations' => $ideaBalanceRecommendations,
        ]);
    }

    /**
     * Persist a new project idea following the role specific business rules.
     */
    public function store(Request $request): View|RedirectResponse
    {
        [$user, $isProfessor, $isStudent, $isResearchStaff] = $this->ensureRoleAccess(true);

        try {
            if ($isResearchStaff) {
                abort(403, 'Research staff members cannot create project ideas.');
            }

            if (! AcademicCalendarService::isProcessWindowOpen(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)) {
                return view(
                    'academic-calendar.unavailable',
                    AcademicCalendarService::unavailableActivityViewData(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)
                );
            }

            $activeAcademicPeriod = AcademicCalendarService::currentActivePeriod();
            $proposalWindow = AcademicCalendarService::currentWindowForProcess(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL);

            if (! $activeAcademicPeriod || ! $proposalWindow) {
                return view(
                    'academic-calendar.unavailable',
                    AcademicCalendarService::unavailableActivityViewData(AcademicProcessWindow::PROCESS_IDEA_PROPOSAL)
                );
            }

            if ($isProfessor) {
                $professorProfile = $this->resolveProfessorProfile($user);

                return $this->persistProfessorProject($request, $professorProfile, null, $activeAcademicPeriod, $proposalWindow);
            }

            $studentAcademicProgress = app(StudentAcademicProgressService::class);

            if (! $studentAcademicProgress->canCreateProposal($user->student, $activeAcademicPeriod)) {
                abort(403, $studentAcademicProgress->blockedProposalMessage($user->student, $activeAcademicPeriod));
            }

            return $this->persistStudentProject($request, $user->student, null, $activeAcademicPeriod, $proposalWindow);
        } catch (\Throwable $exception) {
            Log::error('Failed to register project idea.', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', app()->environment('local')
                    ? $exception->getMessage()
                    : 'Unexpected error. Please try again later.');
        }
    }

    /**
     * Display the details of a project, including its latest version.
     */
    public function show(Project $project): View
    {
        $project->load([
            'thematicArea.investigationLine',
            'projectStatus',
            'professors.user',
            'professors.cityProgram.program',
            'students',
            'contentFrameworks.framework',
            'versions' => static fn ($relation) => $relation
                ->with(['contentVersions.content'])
                ->orderByDesc('created_at'),
        ]);

        $latestVersion = $project->versions->first();
        $contentValues = $this->mapContentValues($latestVersion);

        $normalizedStatus = $this->normalizeStatusName($project->projectStatus->name ?? '');
        $reviewComment = null;

        if ($normalizedStatus === 'devuelto para correccion' && $latestVersion) {
            $reviewContent = $latestVersion->contentVersions
                ->first(static function (ContentVersion $contentVersion): bool {
                    return Str::lower($contentVersion->content->name ?? '') === 'comentarios';
                });

            $reviewComment = $reviewContent?->value;
        }

        $user = AuthUserHelper::fullUser();
        $statusName = $project->projectStatus->name ?? 'Sin estado';
        $canEdit = $this->isReturnedForCorrection($project);

        return view('projects.show', [
            'project' => $project,
            'latestVersion' => $latestVersion,
            'contentValues' => $contentValues,
            'frameworksSelected' => $project->contentFrameworks,
            'isProfessor' => in_array($user?->role, ['professor', 'committee_leader'], true),
            'isStudent' => $user?->role === 'student',
            'isCommitteeLeader' => $user?->role === 'committee_leader',
            'isResearchStaff' => $user?->role === 'research_staff',
            'reviewComment' => $reviewComment,
            'canEdit' => $canEdit,
            'statusName' => $statusName,
            'canViewVersionHistory' => $this->canViewVersionHistory($project, $user),
        ]);
    }

    /**
     * Provide an AJAX friendly list of professors and committee leaders to associate with a project.
     */
    public function participants(Request $request): JsonResponse
    {
        [$user, $isProfessor] = $this->ensureRoleAccess();

        if (! $isProfessor) {
            abort(403, 'Only professors and committee leaders can browse participants.');
        }

        $requestedIds = collect($request->input('ids', []))
            ->filter(static fn ($id) => is_numeric($id))
            ->map(static fn ($id) => (int) $id)
            ->unique();

        if ($requestedIds->isNotEmpty()) {
            $prefetched = $this->participantQuery(null)
                ->whereIn('professors.id', $requestedIds)
                ->get();

            return response()->json([
                'data' => $prefetched
                    ->map(fn (Professor $professor) => $this->presentParticipant($professor))
                    ->values(),
                'meta' => null,
            ]);
        }

        $activeProfessor = $this->resolveProfessorProfile($user);
        $excludeId = $activeProfessor?->id;
        $term = trim((string) $request->input('q', ''));

        $query = $this->participantQuery($excludeId);

        $programFilter = $request->input('program_id');
        if ($programFilter !== null && $programFilter !== '') {
            $programId = (int) $programFilter;

            $query->whereHas('cityProgram', static function (Builder $builder) use ($programId) {
                $builder->where('program_id', $programId);
            });
        }

        if ($term !== '') {
            $normalizedTerm = mb_strtolower($term);

            $query->where(static function (Builder $builder) use ($normalizedTerm, $term) {
                $builder->whereRaw('LOWER(professors.name) like ?', ["%{$normalizedTerm}%"])
                    ->orWhereRaw('LOWER(professors.last_name) like ?', ["%{$normalizedTerm}%"])
                    ->orWhere('professors.card_id', 'like', "%{$term}%")
                    ->orWhereRaw('LOWER(professors.mail) like ?', ["%{$normalizedTerm}%"])
                    ->orWhereHas('user', static function (Builder $userQuery) use ($normalizedTerm) {
                        $userQuery->whereRaw('LOWER(email) like ?', ["%{$normalizedTerm}%"]);
                    });
            });
        }

        $participants = $query->get();

        return response()->json([
            'data' => $participants
                ->map(fn (Professor $professor) => $this->presentParticipant($professor))
                ->values(),
            'meta' => null,
        ]);
    }

    /**
     * Render a simple view that consumes the JSON endpoint to list participants.
     */
    public function participantsPage(): View
    {
        [, $isProfessor] = $this->ensureRoleAccess();

        if (! $isProfessor) {
            abort(403);
        }

        $programs = Program::orderBy('name')->get();

        return view('participants.index', [
            'programs' => $programs,
        ]);
    }

    /**
     * Build the base query for participants, optionally excluding the authenticated profile.
     */
    protected function participantQuery(?int $excludeProfessorId = null): Builder
    {
        return Professor::query()
            ->select('professors.*')
            ->with(['user', 'cityProgram.program', 'cityProgram.city'])
            ->whereHas('user', static function (Builder $builder) {
                $builder->whereIn('role', ['professor', 'committee_leader', 'committe_leader']);
            })
            ->whereNull('professors.deleted_at')
            ->when($excludeProfessorId, static function (Builder $builder, int $exclude) {
                $builder->where('professors.id', '!=', $exclude);
            })
            ->orderBy('professors.last_name')
            ->orderBy('professors.name');
    }

    /**
     * Normalize the participant payload so the Blade and JS layers consume the same shape.
     */
    protected function presentParticipant(Professor $professor): array
    {
        return [
            'id' => $professor->id,
            'name' => trim(($professor->name ?? '') . ' ' . ($professor->last_name ?? '')),
            'document' => $professor->card_id,
            'email' => $professor->mail ?? $professor->user?->email,
            'program' => optional($professor->cityProgram?->program)->name,
            'program_id' => $professor->cityProgram?->program_id,
            'program_city' => optional($professor->cityProgram?->city)->name,
        ];
    }

    /**
     * Resolve the professor profile associated with the authenticated user.
     */
    protected function resolveProfessorProfile(?User $user): ?Professor
    {
        if (! $user) {
            return null;
        }

        if ($user->relationLoaded('professor') || array_key_exists('professor', $user->getRelations())) {
            if ($user->professor) {
                return $user->professor;
            }
        }

        return Professor::query()->where('user_id', $user->id)->first();
    }

    /**
     * Display the edit form with the existing project information.
     */
    public function edit(Project $project): View
    {
        $statusName = $this->normalizeStatusName($project->projectStatus->name ?? '');

        if ($statusName === 'pendiente de aprobacion') {
            abort(403, 'Projects pending approval cannot be edited.');
        }

        if (! $this->isReturnedForCorrection($project)) {
            abort(403, 'Solo los proyectos devueltos para correccion pueden ser editados.');
        }

        [$user, $isProfessor, $isStudent, $isResearchStaff, $isCommitteeLeader] = $this->ensureRoleAccess(true);
        $activeProfessor = $this->resolveProfessorProfile($user);
        $this->authorizeProjectAccess($project, $user->id, $isProfessor, $isStudent, $isResearchStaff);

        if ($isResearchStaff) {
            abort(403, 'El personal de investigaciones no puede editar proyectos.');
        }

        if ($isProfessor) {
            $researchGroupId = $activeProfessor?->cityProgram?->program?->research_group_id;
        } else {
            $researchGroupId = $user->student?->cityProgram?->program?->research_group_id;
        }

        $project->load([
            'thematicArea',
            'professors',
            'students',
            'versions' => static fn ($relation) => $relation
                ->with(['contentVersions.content'])
                ->orderByDesc('created_at'),
        ]);

        $latestVersion = $project->versions->first();
        $contentValues = $this->mapContentValues($latestVersion);

        $versionComment = null;
        if ($latestVersion) {
            $commentContent = $latestVersion->contentVersions->first(function ($cv) {
                return $this->normalizeContentName($cv->content->name ?? '') === 'comentarios';
            });

            $versionComment = $commentContent->value ?? null;
        }

        $cities = City::query()->orderBy('name')->get();
        $programs = Program::query()->with('researchGroup')->orderBy('name')->get();
        $investigationLines = InvestigationLine::where('research_group_id', $researchGroupId)
            ->whereNull('deleted_at')
            ->get();
        $thematicAreas = ThematicArea::query()->orderBy('name')->get();
        $selectedInvestigationLineId = $project->thematicArea->investigation_line_id ?? null;
        $selectedThematicAreaId = $project->thematic_area_id ?? null;

        $prefill = [
            'delivery_date' => Carbon::now()->format('Y-m-d'),
        ];

        $availableStudents = collect();
        $availableProfessors = collect();
        $frameworks = collect();
        $selectedContentFrameworkIds = [];

        $hasProfessorParticipants = $project->professors->isNotEmpty();
        $hasStudentParticipants = $project->students->isNotEmpty();

        $useProfessorForm = $isProfessor || ($isResearchStaff && $hasProfessorParticipants);
        $useStudentForm = $isStudent || ($isResearchStaff && ! $hasProfessorParticipants && $hasStudentParticipants);

        if ($useProfessorForm) {
            $contextProfessor = $isProfessor ? $activeProfessor : $project->professors->first();
            if (! $contextProfessor) {
                abort(403, 'Professor profile required to edit proposals.');
            }

            $prefill = array_merge($prefill, [
                'first_name' => $contextProfessor->name,
                'last_name' => $contextProfessor->last_name,
                'email' => $contextProfessor->mail ?? $contextProfessor->user?->email,
                'phone' => $contextProfessor->phone,
                'city_id' => optional($contextProfessor->cityProgram)->city_id,
                'program_id' => optional($contextProfessor->cityProgram)->program_id,
            ]);

            $frameworks = Framework::with('contentFrameworks')
                ->where('end_year', '>=', now()->year)
                ->orderBy('name')
                ->get();

            $selectedContentFrameworkIds = $project
                ->contentFrameworkProjects()
                ->pluck('content_framework_id')
                ->toArray();

            $availableProfessors = $this->participantQuery(optional($contextProfessor)->id)
                ->get()
                ->map(fn (Professor $participant) => $this->presentParticipant($participant));
        } elseif ($useStudentForm) {
            $contextStudent = $isStudent ? $user->student : $project->students->first();
            if (! $contextStudent) {
                abort(403, 'Student profile required to edit proposals.');
            }

            $cityProgram = $contextStudent->cityProgram;
            $program = $cityProgram?->program;
            $researchGroup = $program?->researchGroup;

            $frameworks = Framework::with('contentFrameworks')
                ->where('end_year', '>=', now()->year)
                ->orderBy('name')
                ->get();

            $selectedContentFrameworkIds = $project
                ->contentFrameworkProjects()
                ->pluck('content_framework_id')
                ->toArray();

            $prefill = array_merge($prefill, [
                'first_name' => $contextStudent->name,
                'last_name' => $contextStudent->last_name,
                'card_id' => $contextStudent->card_id,
                'email' => $contextStudent->user?->email,
                'phone' => $contextStudent->phone,
                'city_id' => $cityProgram?->city_id,
                'program_id' => $program?->id,
                'research_group' => $researchGroup?->name,
            ]);

            $availableStudents = Student::query()
                ->whereHas('projects', function ($query) use ($project) {
                    $query->where('project_id', $project->id);
                })
                ->where('id', '!=', $contextStudent->id)
                ->orderBy('last_name')
                ->orderBy('name')
                ->get();
        } else {
            abort(403, 'Project participants are required to edit this proposal.');
        }

        return view('projects.edit', [
            'project' => $project,
            'cities' => $cities,
            'programs' => $programs,
            'investigationLines' => $investigationLines,
            'thematicAreas' => $thematicAreas,
            'prefill' => $prefill,
            'contentValues' => $contentValues,
            'isProfessor' => $useProfessorForm,
            'isStudent' => $useStudentForm,
            'isCommitteeLeader' => $isCommitteeLeader,
            'isResearchStaff' => $isResearchStaff,
            'availableStudents' => $availableStudents,
            'availableProfessors' => $availableProfessors,
            'frameworks' => $frameworks,
            'selectedContentFrameworkIds' => $selectedContentFrameworkIds,
            'selectedInvestigationLineId' => $selectedInvestigationLineId,
            'selectedThematicAreaId' => $selectedThematicAreaId,
            'versionComment' => $versionComment,
            'isEdit' => true,
        ]);
    }

    /**
     * Update the project information by creating a new version with the submitted content.
     */
    public function update(Request $request, Project $project): RedirectResponse
    {
        $statusName = $this->normalizeStatusName($project->projectStatus->name ?? '');

        if ($statusName === 'pendiente de aprobacion') {
            abort(403, 'Projects pending approval cannot be edited.');
        }

        if (! $this->isReturnedForCorrection($project)) {
            abort(403, 'Solo los proyectos devueltos para correccion pueden ser editados.');
        }

        [$user, $isProfessor, $isStudent, $isResearchStaff] = $this->ensureRoleAccess(true);
        $this->authorizeProjectAccess($project, $user->id, $isProfessor, $isStudent, $isResearchStaff);

        $project->loadMissing(['professors', 'students']);

        try {
            if ($isProfessor) {
                return $this->persistProfessorProject($request, $this->resolveProfessorProfile($user), $project);
            }

            if ($isStudent) {
                return $this->persistStudentProject($request, $user->student, $project);
            }

            if ($isResearchStaff) {
                abort(403, 'Pidele al creador del proyecto que lo edite y envie a revision de nuevo.');
            }
        } catch (\Throwable $exception) {
            Log::error('Failed to update project idea.', [
                'project_id' => $project->id,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', app()->environment('local')
                    ? $exception->getMessage()
                    : 'Unexpected error. Please try again later.');
        }
    }

    /**
     * Guard access to edit/update operations ensuring the user participates in the project.
     */
    protected function authorizeProjectAccess(Project $project, int $userId, bool $isProfessor, bool $isStudent, bool $isResearchStaff): void
    {
        if ($isResearchStaff) {
            return;
        }

        if ($isProfessor) {
            $user = AuthUserHelper::fullUser();
            $professor = $this->resolveProfessorProfile($user);

            if (! $professor || ! $project->professors->contains('id', $professor->id)) {
                abort(403, 'You are not assigned to this project.');
            }
        } elseif ($isStudent) {
            $user = AuthUserHelper::fullUser();
            $student = $user->student;

            if (! $student || ! $project->students->contains('id', $student->id)) {
                abort(403, 'You are not assigned to this project.');
            }
        } else {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * Normalize a project title using the same rules as the Project model mutator.
     */
    protected function normalizeTitle(string $title): string
    {
        return Str::of($title)->squish()->title()->toString();
    }

    /**
     * Retrieve the content identifier by name and cache the lookup.
     */
    protected function contentId(string $name): int
    {
        $normalizedName = $this->normalizeContentName($name);

        if (empty($this->contentCache)) {
            $this->contentCache = Content::query()
                ->get(['id', 'name'])
                ->mapWithKeys(function (Content $content) {
                    return [$this->normalizeContentName($content->name) => $content->id];
                })
                ->toArray();
        }

        if (! array_key_exists($normalizedName, $this->contentCache)) {
            throw new \RuntimeException("Content '{$name}' not found in catalog.");
        }

        return $this->contentCache[$normalizedName];
    }

    /**
     * Resolve the identifier for the status representing "waiting evaluation".
     */
    protected function waitingEvaluationStatusId(): int
    {
        if ($this->waitingStatusId !== null) {
            return $this->waitingStatusId;
        }

        $status = ProjectStatus::query()
            ->whereIn('name', ['waiting evaluation', 'Pendiente de aprobacion'])
            ->orderByRaw("CASE WHEN name = 'waiting evaluation' THEN 0 ELSE 1 END")
            ->first();

        if (! $status) {
            throw new \RuntimeException('Waiting evaluation status is missing from the catalog.');
        }

        $this->waitingStatusId = $status->id;

        return $this->waitingStatusId;
    }

    /**
     * Map the content values for the provided version into a keyed collection.
     *
     * @return array<string, string>
     */
    protected function mapContentValues(?Version $version): array
    {
        if (! $version) {
            return [];
        }

        return $version->contentVersions
            ->filter(static fn (ContentVersion $contentVersion) => $contentVersion->content !== null)
            ->mapWithKeys(function (ContentVersion $contentVersion) {
                return [$this->contentDisplayName($contentVersion->content->name) => $contentVersion->value];
            })
            ->toArray();
    }

    /**
     * Persist the project data for a professor either creating or updating a record.
     */
    protected function persistProfessorProject(
        Request $request,
        ?Professor $professor,
        ?Project $project = null,
        ?AcademicPeriod $activeAcademicPeriod = null,
        ?AcademicProcessWindow $proposalWindow = null
    ): RedirectResponse
    {
        if (! $professor) {
            abort(403, 'Professor profile required to complete this action.');
        }

        $assignedProgramId = optional($professor->cityProgram)->program_id;

        if (! $assignedProgramId) {
            abort(403, 'A program assignment is required before submitting projects.');
        }

        $request->merge(['program_id' => $assignedProgramId]);

        $baseRules = [
            'city_id' => ['required', 'exists:cities,id'],
            'program_id' => ['required', 'integer', Rule::in([$assignedProgramId])],
            'investigation_line_id' => ['required', 'exists:investigation_lines,id'],
            'thematic_area_id' => [
                'required',
                Rule::exists('thematic_areas', 'id')->where(fn ($query) => $query->where('investigation_line_id', $request->integer('investigation_line_id'))),
            ],
            'title' => ['required', 'string', 'max:255'],
            'evaluation_criteria' => ['required', 'string'],
            'students_count' => ['required', 'integer', 'min:1', 'max:3'],
            'execution_time' => ['required', 'string', 'max:255'],
            'viability' => ['required', 'string'],
            'relevance' => ['required', 'string'],
            'teacher_availability' => ['required', 'string'],
            'title_objectives_quality' => ['required', 'string'],
            'general_objective' => ['required', 'string'],
            'description' => ['required', 'string'],
            'contact_first_name' => ['required', 'string', 'max:50'],
            'contact_last_name' => ['required', 'string', 'max:50'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'associated_professors' => ['nullable', 'array'],
            'associated_professors.*' => ['integer', Rule::exists('professors', 'id')->whereNull('deleted_at')],
            'content_frameworks' => ['required', 'array'],
            'content_frameworks.*' => ['required', Rule::exists('content_frameworks', 'id')],
        ];

        $validated = $request->validate($baseRules);
        $isUpdate = $project !== null;
        $normalizedTitle = $this->normalizeTitle($validated['title']);

        $professorIds = collect($validated['associated_professors'] ?? [])
            ->filter(static fn ($id) => $id !== null)
            ->push($professor->id)
            ->unique()
            ->values()
            ->all();

        $sortedProfessorIds = $professorIds;
        sort($sortedProfessorIds);

        $duplicateProject = Project::query()
            ->when($project, static fn ($query) => $query->where('id', '!=', $project->id))
            ->where('title', $normalizedTitle)
            ->get()
            ->first(static function (Project $existing) use ($sortedProfessorIds) {
                $existingProfessorIds = $existing->professors()->pluck('professors.id')->sort()->values()->all();

                return $existingProfessorIds === $sortedProfessorIds;
            });

        if ($duplicateProject) {
            return back()
                ->withInput()
                ->with('error', 'A project with the same title and professor team already exists.');
        }

        $activeAcademicPeriod ??= AcademicCalendarService::currentActivePeriodOrFail();
        $proposalWindow ??= AcademicCalendarService::ensureProcessWindowOpenOrFail(
            AcademicProcessWindow::PROCESS_IDEA_PROPOSAL
        );

        DB::beginTransaction();

        try {
            $professor->fill([
                'name' => $validated['contact_first_name'],
                'last_name' => $validated['contact_last_name'],
                'mail' => $validated['contact_email'],
                'phone' => $validated['contact_phone'],
            ])->save();

            if ($professor->user && $professor->user->email !== $validated['contact_email']) {
                $professor->user->email = $validated['contact_email'];
                $professor->user->save();
            }

            if ($project) {
                $project->fill([
                    'title' => $normalizedTitle,
                    'evaluation_criteria' => $validated['evaluation_criteria'],
                    'thematic_area_id' => $validated['thematic_area_id'],
                    'project_status_id' => $this->waitingEvaluationStatusId(),
                ])->save();
            } else {
                $project = Project::create([
                    'title' => $normalizedTitle,
                    'evaluation_criteria' => $validated['evaluation_criteria'],
                    'thematic_area_id' => $validated['thematic_area_id'],
                    'project_status_id' => $this->waitingEvaluationStatusId(),
                    'proposal_academic_period_id' => $activeAcademicPeriod->id,
                    'proposed_at' => now(),
                ]);
            }

            $project->professors()->sync($professorIds);

            $contentFrameworkIds = array_values(array_filter($validated['content_frameworks'] ?? []));
            $project->contentFrameworks()->sync($contentFrameworkIds);

            $contentMap = [
                'Titulo' => $project->title,
                'Cantidad de estudiantes' => (string) $validated['students_count'],
                'Tiempo de ejecucion' => $validated['execution_time'],
                'Viabilidad' => $validated['viability'],
                'Pertinencia con el grupo de investigacion y con el programa' => $validated['relevance'],
                'Disponibilidad de docentes para su direccion y calificacion' => $validated['teacher_availability'],
                'Calidad y correspondencia entre titulo y objetivo' => $validated['title_objectives_quality'],
                'Objetivo general del proyecto' => $validated['general_objective'],
                'Descripcion del proyecto de investigacion' => $validated['description'],
            ];

            $this->storeProjectVersion($project, $contentMap, $professor->user_id);

            if (! $isUpdate) {
                AcademicCalendarService::recordProjectStage(
                    $project,
                    'proposal_created',
                    $activeAcademicPeriod,
                    $professor->user_id,
                    'Proyecto propuesto por profesor.',
                    ['proposal_window_id' => $proposalWindow->id]
                );
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $message = $isUpdate
            ? 'Project idea updated and set to waiting evaluation'
            : 'Project idea registered and set to waiting evaluation';

        return redirect()
            ->route('projects.index')
            ->with('success', $message);
    }

    /**
     * Persist the project data for a student either creating or updating a record.
     */
    protected function persistStudentProject(
        Request $request,
        ?Student $student,
        ?Project $project = null,
        ?AcademicPeriod $activeAcademicPeriod = null,
        ?AcademicProcessWindow $proposalWindow = null
    ): RedirectResponse
    {
        if (! $student) {
            abort(403, 'Student profile required to complete this action.');
        }

        $baseRules = [
            'city_id' => ['required', 'exists:cities,id'],
            'investigation_line_id' => ['required', 'exists:investigation_lines,id'],
            'thematic_area_id' => [
                'required',
                Rule::exists('thematic_areas', 'id')->where(fn ($query) => $query->where('investigation_line_id', $request->integer('investigation_line_id'))),
            ],
            'title' => ['required', 'string', 'max:255'],
            'general_objective' => ['required', 'string'],
            'description' => ['required', 'string'],
            'teammate_ids' => ['nullable', 'array', 'max:2'],
            'teammate_ids.*' => [
                'integer',
                Rule::exists('students', 'id')->where(static function ($query) use ($student) {
                    $query->where('city_program_id', $student->city_program_id);
                }),
            ],
            'student_first_name' => ['required', 'string', 'max:50'],
            'student_last_name' => ['required', 'string', 'max:50'],
            'student_card_id' => [
                'required',
                'string',
                'max:25',
                Rule::unique('students', 'card_id')->ignore($student->id),
            ],
            'student_email' => ['required', 'email', 'max:255'],
            'student_phone' => ['nullable', 'string', 'max:20'],
            'content_frameworks' => ['required', 'array'],
            'content_frameworks.*' => ['required', Rule::exists('content_frameworks', 'id')],
        ];

        $validated = $request->validate($baseRules);
        $isUpdate = $project !== null;

        if (! empty($validated['teammate_ids'])) {
            $hasOtherProjects = Student::query()
                ->whereIn('id', $validated['teammate_ids'])
                ->whereHas('projects', function ($query) use ($project) {
                    $query->where('project_id', '!=', $project?->id)
                        ->whereHas('projectStatus', function ($statusQuery) {
                            $statusQuery->whereNotIn('name', ['Rechazado']);
                        });
                })
                ->exists();

            if ($hasOtherProjects) {
                return back()
                    ->withInput()
                    ->with('error', 'Uno o mas companeros seleccionados ya tienen un proyecto registrado.');
            }
        }

        $cityProgram = $student->cityProgram;
        if ($cityProgram && (int) $validated['city_id'] !== (int) $cityProgram->city_id) {
            return back()
                ->withInput()
                ->with('error', 'The selected city does not match your program assignment.');
        }

        $normalizedTitle = $this->normalizeTitle($validated['title']);
        $studentIds = collect($validated['teammate_ids'] ?? [])
            ->push($student->id)
            ->unique()
            ->values()
            ->all();

        $sortedStudentIds = $studentIds;
        sort($sortedStudentIds);

        if (count($studentIds) > 3) {
            return back()
                ->withInput()
                ->with('error', 'A project can only have up to 3 participating students.');
        }

        $activeStatusIds = ProjectStatus::query()
            ->whereIn('name', ['waiting evaluation', 'Pendiente de aprobacion'])
            ->pluck('id');

        $hasActive = $student->projects()
            ->when($project, static fn ($query) => $query->where('projects.id', '!=', $project->id))
            ->whereIn('project_status_id', $activeStatusIds)
            ->exists();

        if ($hasActive) {
            return back()
                ->withInput()
                ->with('error', 'You already have a project idea waiting evaluation.');
        }

        $duplicateProject = Project::query()
            ->when($project, static fn ($query) => $query->where('id', '!=', $project->id))
            ->where('title', $normalizedTitle)
            ->get()
            ->first(static function (Project $existing) use ($sortedStudentIds) {
                $existingStudentIds = $existing->students()->pluck('students.id')->sort()->values()->all();

                return $existingStudentIds === $sortedStudentIds;
            });

        if ($duplicateProject) {
            return back()
                ->withInput()
                ->with('error', 'A project with the same title and student team already exists.');
        }

        $activeAcademicPeriod ??= AcademicCalendarService::currentActivePeriodOrFail();
        $proposalWindow ??= AcademicCalendarService::ensureProcessWindowOpenOrFail(
            AcademicProcessWindow::PROCESS_IDEA_PROPOSAL
        );

        DB::beginTransaction();

        try {
            $student->fill([
                'name' => $validated['student_first_name'],
                'last_name' => $validated['student_last_name'],
                'card_id' => $validated['student_card_id'],
                'phone' => $validated['student_phone'],
            ])->save();

            if ($student->user && $student->user->email !== $validated['student_email']) {
                $student->user->email = $validated['student_email'];
                $student->user->save();
            }

            if ($project) {
                $project->fill([
                    'title' => $normalizedTitle,
                    'evaluation_criteria' => null,
                    'thematic_area_id' => $validated['thematic_area_id'],
                    'project_status_id' => $this->waitingEvaluationStatusId(),
                ])->save();
            } else {
                $project = Project::create([
                    'title' => $normalizedTitle,
                    'evaluation_criteria' => null,
                    'thematic_area_id' => $validated['thematic_area_id'],
                    'project_status_id' => $this->waitingEvaluationStatusId(),
                    'proposal_academic_period_id' => $activeAcademicPeriod->id,
                    'proposed_at' => now(),
                ]);
            }

            $project->students()->sync($studentIds);

            $contentFrameworkIds = array_values(array_filter($validated['content_frameworks'] ?? []));
            $project->contentFrameworks()->sync($contentFrameworkIds);

            $contentMap = [
                'Titulo' => $project->title,
                'Objetivo general del proyecto' => $validated['general_objective'],
                'Descripcion del proyecto de investigacion' => $validated['description'],
            ];

            $this->storeProjectVersion($project, $contentMap, $student->user_id);

            if (! $isUpdate) {
                AcademicCalendarService::recordProjectStage(
                    $project,
                    'proposal_created',
                    $activeAcademicPeriod,
                    $student->user_id,
                    'Proyecto propuesto por estudiante.',
                    ['proposal_window_id' => $proposalWindow->id]
                );
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $message = $isUpdate
            ? 'Project idea updated and set to waiting evaluation'
            : 'Project idea registered and set to waiting evaluation';

        return redirect()
            ->route('projects.index')
            ->with('success', $message);
    }

    /**
     * Determine whether the authenticated user can consult the version history.
     */
    protected function canViewVersionHistory(Project $project, ?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Normalize project status names so comparisons survive accent and casing differences.
     */
    protected function normalizeStatusName(?string $name): string
    {
        return Str::of((string) $name)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }

    /**
     * Determine whether the project is currently pending approval.
     */
    protected function isPendingApproval(Project $project): bool
    {
        return $this->normalizeStatusName($project->projectStatus->name ?? '') === 'pendiente de aprobacion';
    }

    /**
     * Determine whether the project can be corrected and resubmitted.
     */
    protected function isReturnedForCorrection(Project $project): bool
    {
        return $this->normalizeStatusName($project->projectStatus->name ?? '') === 'devuelto para correccion';
    }

    /**
     * Normalize content names so the code works with accented and plain-text catalog values.
     */
    protected function normalizeContentName(?string $name): string
    {
        return Str::of((string) $name)
            ->ascii()
            ->lower()
            ->replace('-', ' ')
            ->squish()
            ->toString();
    }

    /**
     * Convert catalog names into the labels expected by the project forms and history screens.
     */
    protected function contentDisplayName(?string $name): string
    {
        $normalizedName = $this->normalizeContentName($name);

        return [
            'titulo' => 'Titulo',
            'cantidad de estudiantes' => 'Cantidad de estudiantes',
            'tiempo de ejecucion' => 'Tiempo de ejecucion',
            'viabilidad' => 'Viabilidad',
            'pertinencia con el grupo de investigacion y con el programa' => 'Pertinencia con el grupo de investigacion y con el programa',
            'disponibilidad de docentes para su direccion y calificacion' => 'Disponibilidad de docentes para su direccion y calificacion',
            'calidad y correspondencia entre titulo y objetivo' => 'Calidad y correspondencia entre titulo y objetivo',
            'objetivo general del proyecto' => 'Objetivo general del proyecto',
            'descripcion del proyecto de investigacion' => 'Descripcion del proyecto de investigacion',
            'comentarios' => 'Comentarios',
        ][$normalizedName] ?? (string) $name;
    }

    /**
     * Create a version record that captures the current project snapshot.
     */
    protected function storeProjectVersion(Project $project, array $contentMap, ?int $createdByUserId): Version
    {
        $project->load([
            'projectStatus',
            'thematicArea.investigationLine',
            'contentFrameworks.framework',
            'professors.user',
            'students.user',
        ]);

        $version = $project->versions()->create([
            'created_by_user_id' => $createdByUserId,
            'snapshot' => $this->sanitizeSnapshot($this->buildProjectVersionSnapshot($project, $contentMap)),
        ]);

        $this->storeContentValues($version, $contentMap);

        return $version;
    }

    /**
     * Build a portable snapshot so each version preserves the project state of that moment.
     */
    protected function buildProjectVersionSnapshot(Project $project, array $contentMap): array
    {
        return [
            'title' => $project->title,
            'evaluation_criteria' => $project->evaluation_criteria,
            'project_status' => [
                'id' => $project->projectStatus?->id,
                'name' => $project->projectStatus?->name,
            ],
            'thematic_area' => [
                'id' => $project->thematicArea?->id,
                'name' => $project->thematicArea?->name,
            ],
            'investigation_line' => [
                'id' => $project->thematicArea?->investigationLine?->id,
                'name' => $project->thematicArea?->investigationLine?->name,
            ],
            'contents' => collect($contentMap)
                ->mapWithKeys(function ($value, $label) {
                    return [$this->contentDisplayName($label) => (string) $value];
                })
                ->toArray(),
            'frameworks' => $project->contentFrameworks
                ->map(function ($contentFramework) {
                    return [
                        'id' => $contentFramework->id,
                        'name' => $contentFramework->name,
                        'framework' => [
                            'id' => $contentFramework->framework?->id,
                            'name' => $contentFramework->framework?->name,
                        ],
                    ];
                })
                ->values()
                ->all(),
            'participants' => [
                'professors' => $project->professors
                    ->map(function (Professor $professor) {
                        return [
                            'id' => $professor->id,
                            'name' => trim(($professor->name ?? '') . ' ' . ($professor->last_name ?? '')),
                            'email' => $professor->mail ?? $professor->user?->email,
                            'phone' => $professor->phone,
                        ];
                    })
                    ->values()
                    ->all(),
                'students' => $project->students
                    ->map(function (Student $student) {
                        return [
                            'id' => $student->id,
                            'name' => trim(($student->name ?? '') . ' ' . ($student->last_name ?? '')),
                            'card_id' => $student->card_id,
                            'phone' => $student->phone,
                        ];
                    })
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * Sanitize the snapshot payload so it can always be stored as valid UTF-8 JSON.
     */
    protected function sanitizeSnapshot(array $snapshot): array
    {
        return $this->sanitizeSnapshotValue($snapshot);
    }

    /**
     * Recursively normalize keys and values before JSON encoding them.
     */
    protected function sanitizeSnapshotValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitizedKey = is_string($key)
                    ? $this->sanitizeSnapshotString($key)
                    : $key;

                $sanitized[$sanitizedKey] = $this->sanitizeSnapshotValue($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->sanitizeSnapshotString($value);
        }

        return $value;
    }

    /**
     * Normalize strings that may contain mixed encodings before storing JSON snapshots.
     */
    protected function sanitizeSnapshotString(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $sanitized = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);

        if ($sanitized !== false && $sanitized !== '') {
            return $sanitized;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    /**
     * Persist each content value in the content_version table.
     */
    protected function storeContentValues(Version $version, array $contentMap): void
    {
        foreach ($contentMap as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            ContentVersion::create([
                'content_id' => $this->contentId($name),
                'version_id' => $version->id,
                'value' => (string) $value,
            ]);
        }
    }
}
