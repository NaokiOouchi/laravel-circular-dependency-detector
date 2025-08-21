<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modules Path
    |--------------------------------------------------------------------------
    |
    | Define where your modules are located. You can specify multiple paths
    | for different module locations or use a single path.
    |
    | Examples:
    | - app_path('Modules')           // app/Modules
    | - app_path('Domain')            // app/Domain  
    | - base_path('src')              // src/
    | - base_path('packages')         // packages/
    | - [app_path('Modules'), base_path('packages')]  // Multiple paths
    |
    */
    'modules_path' => app_path('Modules'),
    
    /*
    |--------------------------------------------------------------------------
    | Namespace Patterns
    |--------------------------------------------------------------------------
    |
    | Define namespace patterns for module detection.
    | Use {MODULE} as a placeholder for the module name.
    |
    | Examples:
    | - 'App\\Modules\\{MODULE}'     // App\Modules\UserModule
    | - 'Packages\\{MODULE}'         // Packages\UserModule
    | - 'Domain\\{MODULE}'           // Domain\UserModule
    | - 'Acme\\{MODULE}'            // Custom vendor namespace
    |
    */
    'namespace_patterns' => [
        'App\\Modules\\{MODULE}',
        // 'Packages\\{MODULE}',        // Uncomment for packages/ structure
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Scan Patterns
    |--------------------------------------------------------------------------
    |
    | Define which directories within modules should be scanned.
    | Each subdirectory in modules_path will be treated as a module,
    | and these patterns define which directories to scan within each module.
    |
    */
    'scan_patterns' => [
        // Traditional Laravel structure
        'controllers' => 'Controllers',
        'services' => 'Services',
        'repositories' => 'Repositories',
        'providers' => 'Providers',
        'models' => 'Models',
        'jobs' => 'Jobs',
        'listeners' => 'Listeners',
        'actions' => 'Actions',
        'commands' => 'Commands',
        'handlers' => 'Handlers',
        
        // DDD structure
        'domain' => 'Domain',
        'application' => 'Application',
        'infrastructure' => 'Infrastructure',
        'presentation' => 'Presentation',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Ignore Patterns
    |--------------------------------------------------------------------------
    |
    | Files and directories matching these patterns will be ignored.
    | Supports wildcards (*) for pattern matching.
    |
    */
    'ignore_patterns' => [
        '*/Tests/*',
        '*/tests/*',
        '*/Test/*',
        '*/Migrations/*',
        '*/Database/Migrations/*',
        '*/Database/Seeders/*',
        '*/Database/Factories/*',
        '*/Resources/views/*',
        '*/Resources/lang/*',
        '*/Resources/js/*',
        '*/Resources/css/*',
        '*/Resources/sass/*',
        '*.blade.php',
        '*/config/*',
        '*/routes/*',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Allowed Dependencies
    |--------------------------------------------------------------------------
    |
    | These namespace parts are always allowed and won't be considered
    | as circular dependencies. Useful for shared contracts and DTOs.
    |
    */
    'allowed_dependencies' => [
        'Contracts',
        'Interfaces',
        'Events',
        'Exceptions',
        'DTOs',
        'DataTransferObjects',
        'Enums',
        'ValueObjects',
        'Shared',
        'Common',
        'Core',
        'Base',
    ],
];