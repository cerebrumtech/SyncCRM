<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::signIn');
$routes->post('logout', 'Auth::logout');
$routes->get('setup', 'Setup::index');
$routes->post('setup', 'Setup::create');
$routes->get('invite/(:segment)', 'Invite::show/$1');
$routes->post('invite/(:segment)', 'Invite::accept/$1');
$routes->get('install', 'Install::index');
$routes->post('install', 'Install::run');

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $r) {
    $r->get('dashboard', 'Dashboard::index');

    $r->get('contacts', 'Contacts::index');
    $r->post('contacts', 'Contacts::create');
    $r->get('contacts/(:num)', 'Contacts::show/$1');
    $r->post('contacts/(:num)', 'Contacts::update/$1');
    $r->post('contacts/(:num)/delete', 'Contacts::delete/$1');
    $r->post('contacts/(:num)/merge', 'Contacts::merge/$1');

    $r->get('companies', 'Companies::index');
    $r->post('companies', 'Companies::create');
    $r->get('companies/(:num)', 'Companies::show/$1');
    $r->post('companies/(:num)', 'Companies::update/$1');
    $r->post('companies/(:num)/delete', 'Companies::delete/$1');

    $r->get('deals', 'Deals::index');
    $r->post('deals', 'Deals::create');
    $r->get('deals/(:num)', 'Deals::show/$1');
    $r->post('deals/(:num)', 'Deals::update/$1');
    $r->post('deals/(:num)/delete', 'Deals::delete/$1');
    $r->post('deals/(:num)/move', 'Deals::move/$1');
    $r->post('deals/(:num)/handoff', 'Deals::handoff/$1');
    $r->post('deals/(:num)/lost-reason', 'Deals::lostReason/$1');
    $r->post('deals/(:num)/line-items', 'Deals::lineItems/$1');

    $r->post('notes', 'Records::addNote');
    $r->post('notes/(:num)/delete', 'Records::deleteNote/$1');
    $r->post('attachments', 'Records::upload');
    $r->post('attachments/(:num)/delete', 'Records::deleteAttachment/$1');
    $r->get('files/(:num)', 'Records::file/$1');
    $r->post('shares', 'Records::share');
    $r->post('shares/(:num)/delete', 'Records::unshare/$1');

    $r->get('products', 'Products::index');
    $r->post('products', 'Products::create');
    $r->post('products/(:num)', 'Products::update/$1');
    $r->post('products/(:num)/delete', 'Products::delete/$1');

    $r->get('activities', 'Activities::index');
    $r->post('activities', 'Activities::create');
    $r->post('activities/(:num)', 'Activities::update/$1');
    $r->post('activities/(:num)/status', 'Activities::status/$1');
    $r->post('activities/(:num)/delete', 'Activities::delete/$1');

    $r->post('views', 'Views::create');
    $r->post('views/(:num)/delete', 'Views::delete/$1');

    $r->get('api/search/(:segment)', 'Api::search/$1');
    $r->get('export/(:segment)', 'Export::run/$1');

    $r->group('settings', ['filter' => 'admin', 'namespace' => 'App\Controllers\Settings'], static function (RouteCollection $s) {
        $s->get('', 'Users::index');
        $s->get('users', 'Users::index');
        $s->post('users/invite', 'Users::invite');
        $s->post('users/(:num)/role', 'Users::role/$1');
        $s->post('users/(:num)/deactivate', 'Users::deactivate/$1');
        $s->post('users/(:num)/reactivate', 'Users::reactivate/$1');
        $s->post('users/invites/(:num)/revoke', 'Users::revoke/$1');
        $s->get('teams', 'Teams::index');
        $s->post('teams', 'Teams::create');
        $s->post('teams/(:num)/delete', 'Teams::delete/$1');
        $s->post('teams/(:num)/members', 'Teams::members/$1');
        $s->get('pipelines', 'Pipelines::index');
        $s->post('pipelines', 'Pipelines::create');
        $s->post('pipelines/(:num)', 'Pipelines::update/$1');
        $s->post('pipelines/(:num)/delete', 'Pipelines::delete/$1');
        $s->post('pipelines/(:num)/stages', 'Pipelines::addStage/$1');
        $s->post('stages/(:num)', 'Pipelines::updateStage/$1');
        $s->post('stages/(:num)/delete', 'Pipelines::deleteStage/$1');
        $s->post('stages/(:num)/move', 'Pipelines::moveStage/$1');
        $s->get('fields', 'Fields::index');
        $s->post('fields', 'Fields::create');
        $s->post('fields/(:num)', 'Fields::update/$1');
        $s->post('fields/(:num)/delete', 'Fields::delete/$1');
        $s->get('import', 'Import::index');
        $s->post('import/upload', 'Import::upload');
        $s->post('import/run', 'Import::run');
        $s->get('organization', 'Organization::index');
        $s->post('organization', 'Organization::update');
        $s->post('organization/dedupe', 'Organization::dedupe');
        $s->get('audit', 'Audit::index');
    });
});
