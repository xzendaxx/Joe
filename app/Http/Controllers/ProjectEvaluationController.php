<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Models\Professor;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Services\AcademicCalendar\AcademicCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProjectEvaluationController extends Controller
{
    public function index()
    {
        $professor = Professor::where('user_id', Auth::id())->where('committee_leader', true)->whereNull('deleted_at')->first();

        if (! $professor || ! $professor->city_program_id) {
            abort(403, 'No se pudo determinar el programa del líder de comité.');
        }

        $cityProgramId = $professor->city_program_id;

        $projects = Project::whereHas('projectStatus', function ($query) {
                $query->whereIn('name', ['Pendiente de aprobacion']);
            })
            ->where(function ($query) use ($cityProgramId) {
                $query->whereHas('students', function ($sub) use ($cityProgramId) {
                    $sub->where('city_program_id', $cityProgramId);
                })->orWhereHas('professors', function ($sub) use ($cityProgramId) {
                    $sub->where('city_program_id', $cityProgramId);
                });
            })
            ->with(['projectStatus', 'thematicArea.investigationLine', 'versions.contentVersions.content', 'contentFrameworkProjects.contentFramework.framework', 'students', 'professors'])
            ->get();

        return view('projects.evaluation.index', compact('projects'));
    }

    public function show(Project $project)
    {
        $project->load([
            'thematicArea.investigationLine',
            'projectStatus',
            'professors.user',
            'professors.cityProgram.program',
            'students',
            'contentFrameworks.framework',
            'versions' => static fn ($relation) => $relation->with(['contentVersions.content'])->orderByDesc('created_at'),
        ]);

        $latestVersion = $project->versions()->latest('created_at')->first();
        $contentValues = [];
        if ($latestVersion) {
            foreach ($latestVersion->contentVersions as $cv) {
                $label = $cv->content->label ?? $cv->content->name ?? 'Campo';
                $contentValues[$label] = $cv->value ?? '-';
            }
        }
        $frameworksSelected = $project->contentFrameworks;

        return view('projects.evaluation.show', compact('project', 'latestVersion', 'contentValues', 'frameworksSelected'));
    }

    public function evaluate(Request $request, Project $project)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:Aprobado,Rechazado,Devuelto para correccion',
            'comments' => 'nullable|string',
        ]);

        $statusName = $validated['status'];
        $isProfessorProject = $project->professors()->exists();
        $isStudentProject = ! $isProfessorProject;

        // Si es proyecto de estudiante y se aprueba, normalmente se asigna.
        // Pero si estamos en modo postulación (Phase 2), tal vez queramos que siga otro flujo?
        // Sin embargo, si el estudiante propuso la idea, ya es suya.
        if ($statusName === 'Aprobado' && $isStudentProject) {
            $statusName = 'Asignado';
        }

        $status = ProjectStatus::whereIn('name', array_unique([$statusName, str_replace(['ó', 'é'], ['o', 'e'], $statusName)]))->first();
        if (! $status) {
            return back()->with('error', "No se encontró el estado '$statusName'.");
        }

        $project->update(['project_status_id' => $status->id]);

        if (in_array($validated['status'], ['Devuelto para correccion'], true)) {
            $latestVersion = $project->versions()->latest('created_at')->first();
            if ($latestVersion) {
                $commentContent = Content::where('name', 'Comentarios')->whereJsonContains('roles', 'committee_leader')->first();
                if ($commentContent) {
                    ContentVersion::create([
                        'version_id' => $latestVersion->id,
                        'content_id' => $commentContent->id,
                        'value' => $validated['comments'] ?? 'Sin comentarios',
                    ]);
                }
            }
        }

        $activePeriod = \App\Models\AcademicPeriod::query()->active()->first() ?? $project->proposalAcademicPeriod;
        $stage = match (Str::lower($validated['status'])) {
            'aprobado' => 'approved',
            'rechazado' => 'rejected',
            'devuelto para corrección', 'devuelto para correccion' => 'returned_for_correction',
            default => 'evaluated',
        };

        AcademicCalendarService::recordProjectStage($project, $stage, $activePeriod, Auth::id(), $validated['comments'] ?? null, ['final_status_name' => $statusName]);

        return redirect()->route('projects.evaluation.index')->with('success', "Evaluación del proyecto '{$project->title}' enviada correctamente con estado: $statusName.");
    }
}
