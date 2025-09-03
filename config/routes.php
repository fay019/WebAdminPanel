<?php
// Routing table: path => Controller@method
return [
    'GET' => [
        '/' => 'DashboardController@index',
        '/dashboard' => 'DashboardController@index',
        '/php_manage' => 'SystemController@phpManage',
        '/lang' => 'I18nController@set',
        // Auth
        '/login' => 'AuthController@loginForm',
        '/logout' => 'AuthController@logout', // GET allowed but will still require CSRF
        // Users
        '/users' => 'UsersController@index',
        '/users/create' => 'UsersController@create',
        '/users/{id}' => 'UsersController@show',
        '/users/{id}/edit' => 'UsersController@edit',
        // API sysinfo
        '/api/sysinfo' => 'DashboardController@api',
        // API energy
        '/energy/status' => 'EnergyController@status',
        // Legacy alias for compatibility
        '/ajax/sysinfo' => 'DashboardController@api',
        // legacy redirects
        '/dashboard.php' => ['redirect' => '/dashboard'],
        '/users_list.php' => ['redirect' => '/users'],
        '/user_new.php' => ['redirect' => '/users/create'],
        '/user_edit.php' => ['redirect' => '/users'],
        '/users/new' => ['redirect' => '/users/create'],
        '/login.php' => ['redirect' => '/login'],
        '/logout.php' => ['redirect' => '/logout'],
    ],
    'POST' => [
        // Auth
        '/login' => 'AuthController@login',
        '/logout' => 'AuthController@logout',
        '/dashboard/sysinfo' => 'DashboardController@sysinfo', // allow POST too if needed
        '/dashboard/power' => 'DashboardController@power',
        '/php_manage' => 'SystemController@phpManage',
        // Users
        '/users' => 'UsersController@store',
        '/users/{id}/update' => 'UsersController@update',
        '/users/{id}/reset-password' => 'UsersController@resetPassword',
        '/users/{id}/delete' => 'UsersController@destroy',
        // Energy toggles
        '/energy/toggle/hdmi' => 'EnergyController@toggleHdmi',
        '/energy/toggle/wifi' => 'EnergyController@toggleWifi',
        '/energy/toggle/bt'   => 'EnergyController@toggleBt',
        // dynamic-like posts
        '/user_edit.php' => 'UsersController@legacyPost',
        '/users_list.php' => 'UsersController@destroy',
        // legacy endpoint
        '/system_power.php' => 'DashboardController@power',
        '/php_manage.php' => 'SystemController@phpManage',
    ],
    'GET_AJAX' => [
        // the existing AJAX pattern /dashboard.php?ajax=sysinfo must remain
        '/dashboard.php?ajax=sysinfo' => 'DashboardController@sysinfo',
        '/dashboard?ajax=sysinfo' => 'DashboardController@sysinfo',
    ],
];