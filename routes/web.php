<?php
return [
    ['GET', '/', 'App\\Controllers\\DashboardController@index'],
    ['GET', '/login', 'App\\Controllers\\AuthController@showLogin'],
    ['POST', '/login', 'App\\Controllers\\AuthController@login'],
    ['GET', '/logout', 'App\\Controllers\\AuthController@logout'],
    ['GET', '/customers', 'App\\Controllers\\CustomerController@index'],
    ['GET', '/quotations', 'App\\Controllers\\QuotationController@index'],
    ['GET', '/quotations/{id}', 'App\\Controllers\\QuotationController@show'],
];
