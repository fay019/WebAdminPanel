<?php
// Routing table: path => Controller@method
return [
    'GET' => [
        '/' => 'DashboardController@index',
        '/dashboard' => 'DashboardController@index',
        '/php_manage' => 'SystemController@phpManage',
        '/lang' => 'I18nController@set',
        // legacy redirects
        '/dashboard.php' => ['redirect' => '/dashboard'],
    ],
    'POST' => [
        '/dashboard/sysinfo' => 'DashboardController@sysinfo', // allow POST too if needed
        '/dashboard/power' => 'DashboardController@power',
        '/php_manage' => 'SystemController@phpManage',
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
