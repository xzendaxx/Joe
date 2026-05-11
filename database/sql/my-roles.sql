
DROP USER IF EXISTS 'db_user'@'%';
CREATE USER IF NOT EXISTS 'db_user'@'%' IDENTIFIED BY '{{DB_USER_PASS}}';

GRANT USAGE ON *.* TO 'db_user'@'%';


GRANT SELECT ON {{DB_DATABASE}}.users TO 'db_user'@'%';

DROP USER IF EXISTS 'db_student'@'%';
CREATE USER IF NOT EXISTS 'db_student'@'%' IDENTIFIED BY '{{DB_STUDENT_PASS}}';

GRANT USAGE ON *.* TO 'db_student'@'%';


GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_student'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_student'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_student'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_student'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_student'@'%';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_student'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.student_project TO 'db_student'@'%';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_student'@'%';


DROP USER IF EXISTS 'db_professor'@'%';
CREATE USER IF NOT EXISTS 'db_professor'@'%' IDENTIFIED BY '{{DB_PROFESSOR_PASS}}';
GRANT USAGE ON *.* TO 'db_professor'@'%';

GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_professor'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_professor'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_professor'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_professor'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_professor'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professor_project TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_professor'@'%';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_professor'@'%';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_professor'@'%';


DROP USER IF EXISTS 'db_research_staff'@'%';
CREATE USER IF NOT EXISTS 'db_research_staff'@'%' IDENTIFIED BY '{{DB_RESEARCH_PASS}}';
GRANT USAGE ON *.* TO 'db_research_staff'@'%';

GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.departments TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.cities TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.city_program TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.programs TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_groups TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.investigation_lines TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.thematic_areas TO 'db_research_staff'@'%';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_research_staff'@'%';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_research_staff'@'%';
GRANT SELECT ON {{DB_DATABASE}}.content_framework_project TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_frameworks TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.frameworks TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.contents TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professors TO 'db_research_staff'@'%';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.students TO 'db_research_staff'@'%';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.users TO 'db_research_staff'@'%';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_staff TO 'db_research_staff'@'%';




DROP USER IF EXISTS 'db_user'@'localhost';
CREATE USER IF NOT EXISTS 'db_user'@'localhost' IDENTIFIED BY '{{DB_USER_PASS}}';

GRANT USAGE ON *.* TO 'db_user'@'localhost';

GRANT SELECT ON {{DB_DATABASE}}.users TO 'db_user'@'localhost';

DROP USER IF EXISTS 'db_student'@'localhost';
CREATE USER IF NOT EXISTS 'db_student'@'localhost' IDENTIFIED BY '{{DB_STUDENT_PASS}}';

GRANT USAGE ON *.* TO 'db_student'@'localhost';

GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_student'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_student'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_student'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_student'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_student'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_student'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.student_project TO 'db_student'@'localhost';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_student'@'localhost';


DROP USER IF EXISTS 'db_professor'@'localhost';
CREATE USER IF NOT EXISTS 'db_professor'@'localhost' IDENTIFIED BY '{{DB_PROFESSOR_PASS}}';
GRANT USAGE ON *.* TO 'db_professor'@'localhost';

GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_professor'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_professor'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_professor'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_professor'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_professor'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professor_project TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_professor'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_professor'@'localhost';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_professor'@'localhost';

DROP USER IF EXISTS 'db_research_staff'@'localhost';
CREATE USER IF NOT EXISTS 'db_research_staff'@'localhost' IDENTIFIED BY '{{DB_RESEARCH_PASS}}';
GRANT USAGE ON *.* TO 'db_research_staff'@'localhost';

GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.departments TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.cities TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.city_program TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.programs TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_groups TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.investigation_lines TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.thematic_areas TO 'db_research_staff'@'localhost';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_research_staff'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_research_staff'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.content_framework_project TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_frameworks TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.frameworks TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.contents TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professors TO 'db_research_staff'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.students TO 'db_research_staff'@'localhost';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.users TO 'db_research_staff'@'localhost';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_staff TO 'db_research_staff'@'localhost';



DROP USER IF EXISTS 'db_user'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'db_user'@'127.0.0.1' IDENTIFIED BY '{{DB_USER_PASS}}';
GRANT USAGE ON *.* TO 'db_user'@'127.0.0.1';


GRANT SELECT ON {{DB_DATABASE}}.users TO 'db_user'@'127.0.0.1';

DROP USER IF EXISTS 'db_student'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'db_student'@'127.0.0.1' IDENTIFIED BY '{{DB_STUDENT_PASS}}';

GRANT USAGE ON *.* TO 'db_student'@'127.0.0.1';


GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_student'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_student'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_student'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_student'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_student'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_student'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.student_project TO 'db_student'@'127.0.0.1';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_student'@'127.0.0.1';

DROP USER IF EXISTS 'db_professor'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'db_professor'@'127.0.0.1' IDENTIFIED BY '{{DB_PROFESSOR_PASS}}';
GRANT USAGE ON *.* TO 'db_professor'@'127.0.0.1';


GRANT SELECT ON {{DB_DATABASE}}.departments TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.cities TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.city_program TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.programs TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.research_groups TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.investigation_lines TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.thematic_areas TO 'db_professor'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_professor'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_framework_project TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.content_frameworks TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.frameworks TO 'db_professor'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_professor'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.contents TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.professors TO 'db_professor'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professor_project TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.students TO 'db_professor'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_professor'@'127.0.0.1';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.users TO 'db_professor'@'127.0.0.1';

DROP USER IF EXISTS 'db_research_staff'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'db_research_staff'@'127.0.0.1' IDENTIFIED BY '{{DB_RESEARCH_PASS}}';
GRANT USAGE ON *.* TO 'db_research_staff'@'127.0.0.1';

GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.departments TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.cities TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.city_program TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.programs TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_groups TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.investigation_lines TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.thematic_areas TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, UPDATE ON {{DB_DATABASE}}.projects TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.project_statuses TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.content_framework_project TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_frameworks TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.frameworks TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.versions TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.content_version TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.contents TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.professors TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.professor_project TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.students TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT ON {{DB_DATABASE}}.student_project TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.users TO 'db_research_staff'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON {{DB_DATABASE}}.research_staff TO 'db_research_staff'@'127.0.0.1';


FLUSH PRIVILEGES;


