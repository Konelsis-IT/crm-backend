$ErrorActionPreference = 'Stop'

$rawInput = [Console]::In.ReadToEnd()

try {
    $payload = $rawInput | ConvertFrom-Json
} catch {
    [Console]::Error.WriteLine('Konelsis safety hook could not parse the tool input and blocked the command.')
    exit 2
}

if ([string]$payload.tool_name -notin @('Bash', 'PowerShell')) {
    exit 0
}

$command = [string]$payload.tool_input.command

if ([string]::IsNullOrWhiteSpace($command)) {
    exit 0
}

$forbiddenPatterns = @(
    @{
        Code = 'LARAVEL_MIGRATION'
        Pattern = '(?i)\bartisan(?:\.bat)?\s+migrate(?::[a-z0-9_-]+)?\b'
        Reason = 'Artisan migration commands are permanently prohibited.'
    },
    @{
        Code = 'DATABASE_WIPE'
        Pattern = '(?i)\bartisan(?:\.bat)?\s+db:wipe\b'
        Reason = 'Database wipe commands are permanently prohibited.'
    },
    @{
        Code = 'LARAVEL_TEST'
        Pattern = '(?i)\bartisan(?:\.bat)?\s+test\b'
        Reason = 'Laravel automated tests are permanently prohibited because project tests may refresh the database.'
    },
    @{
        Code = 'PHP_TEST_RUNNER'
        Pattern = '(?i)(?:^|[\s;&|])(?:[^\s;&|]*[\\/])?(?:phpunit|pest)(?:\.bat)?(?:\s|$)'
        Reason = 'PHPUnit and Pest execution are permanently prohibited.'
    },
    @{
        Code = 'LARAVEL_PINT'
        Pattern = '(?i)(?:^|[\s;&|])(?:php(?:\.exe)?\s+)?(?:[^\s;&|]*[\\/])?pint(?:\.bat)?(?:\s|$)'
        Reason = 'Laravel Pint execution is permanently prohibited.'
    },
    @{
        Code = 'COMPOSER_PROHIBITED_SCRIPT'
        Pattern = '(?i)(?:^|[;&|]\s*)(?:php(?:\.exe)?\s+)?(?:[^\s;&|]*[\\/])?composer(?:\.phar|\.bat)?\s+(?:run(?:-script)?\s+)?(?:setup|test)(?:\s|$)'
        Reason = 'This Composer script invokes a prohibited migration or Laravel test command.'
    },
    @{
        Code = 'DESTRUCTIVE_SQL'
        Pattern = '(?i)\b(?:drop\s+(?:database|schema|table)|truncate\s+(?:table\s+)?[a-z0-9_"\[]+|flush\s+tables|reset\s+(?:master|replica|slave))\b'
        Reason = 'Destructive database reset, drop, truncate, or flush operations are permanently prohibited.'
    }
)

foreach ($rule in $forbiddenPatterns) {
    if ($command -match $rule.Pattern) {
        [Console]::Error.WriteLine("Blocked by Konelsis safety policy [$($rule.Code)]: $($rule.Reason)")
        exit 2
    }
}

exit 0
