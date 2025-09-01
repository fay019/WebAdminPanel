<?php
// Routing table: path => Controller@method
return [
    'GET' => [
        '/' => 'DashboardController@index',
        '/dashboard' => 'DashboardController@index',
        '/php_manage' => 'SystemController@phpManage',
        '/lang' => 'I18nController@set',
        // Users
        '/users' => 'UsersController@index',
        '/users/new' => 'UsersController@create',
        '/users/{id}/edit' => 'UsersController@edit',
        // legacy redirects
        '/dashboard.php' => ['redirect' => '/dashboard'],
        '/users_list.php' => ['redirect' => '/users'],
        '/user_new.php' => ['redirect' => '/users/new'],
        '/user_edit.php' => 'UsersController@edit',
    ],
    'POST' => [
        '/dashboard/sysinfo' => 'DashboardController@sysinfo', // allow POST too if needed
        '/dashboard/power' => 'DashboardController@power',
        '/php_manage' => 'SystemController@phpManage',
        // Users
        '/users' => 'UsersController@store',
        '/users/{id}' => 'UsersController@update',
        '/users/{id}/reset-password' => 'UsersController@resetPassword',
        '/users/{id}/delete' => 'UsersController@destroy',
        // dynamic-like posts
        '/user_edit.php' => 'UsersController@legacyPost',
        '/users_list.php' => 'UsersController@destroy',
        // legacy endpoint
        '/system_power.php' => 'DashboardController@power',
        '/php_manage.php' => 'SystemController@phpManage',
    ],
    'GET_AJAX' => [
        // existing AJAX pattern /dashboard.php?ajax=sysinfo must remain
        '/dashboard.php?ajax=sysinfo' => 'DashboardController@sysinfo',
        '/dashboard?ajax=sysinfo' => 'DashboardController@sysinfo',
    ],
];
