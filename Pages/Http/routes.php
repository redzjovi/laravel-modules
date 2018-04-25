<?php

Route::group(['middleware' => ['web']], function () {
    Route::group(['middleware' => ['auth']], function () {
        Route::group(['middleware' => ['permission:backend pages']], function () {
            Route::resource('backend/pages', '\Modules\Pages\Http\Controllers\Backend\PagesController', ['as' => 'backend', 'parameters' => ['pages' => 'id']]);
            Route::get('backend/pages/{id}/delete', ['as' => 'backend.pages.delete', 'uses' => '\Modules\Pages\Http\Controllers\Backend\PagesController@delete']);
            Route::get('backend/pages/{id}/trash', ['as' => 'backend.pages.trash', 'uses' => '\Modules\Pages\Http\Controllers\Backend\PagesController@trash']);
        });
    });
    Route::get('pages/{name}', ['as' => 'frontend.pages.show', 'uses' => '\Modules\Pages\Http\Controllers\Frontend\PagesController@show']);
});
