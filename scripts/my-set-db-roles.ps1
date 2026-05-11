# Load environment variables from the .env file
$envContent = Get-Content .env -Raw | ConvertFrom-StringData

Write-Host "==> Ejecutando migraciones y seeders..."
php artisan migrate --seed

Write-Host "==> Creando roles..."
$sqlContent = Get-Content "database/sql/my-roles.sql" -Raw

$sqlContent = $sqlContent -replace "{{DB_USER_PASS}}", $envContent.DB_USER_PASS
$sqlContent = $sqlContent -replace "{{DB_DATABASE}}", $envContent.DB_DATABASE
$sqlContent = $sqlContent -replace "{{DB_STUDENT_PASS}}", $envContent.DB_STUDENT_PASS
$sqlContent = $sqlContent -replace "{{DB_PROFESSOR_PASS}}", $envContent.DB_PROFESSOR_PASS
$sqlContent = $sqlContent -replace "{{DB_RESEARCH_STAFF_PASS}}", $envContent.DB_RESEARCH_STAFF_PASS

$tempFile = "temp_roles.sql"
$sqlContent | Out-File $tempFile -Encoding UTF8

$mysqlPath = "D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"

$mysqlArgs = "-u$($envContent.DB_USERNAME) -p$($envContent.DB_PASSWORD) -h$($envContent.DB_HOST) -P$($envContent.DB_PORT) $($envContent.DB_DATABASE) -e `"source $tempFile`""

Start-Process -FilePath $mysqlPath -ArgumentList $mysqlArgs -Wait -NoNewWindow

Remove-Item $tempFile -ErrorAction SilentlyContinue

Write-Host "==> Base de datos inicializada correctamente 🎉"
