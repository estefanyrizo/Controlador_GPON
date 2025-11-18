<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ObtainedCustomersController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OLTController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\ISPController;
use App\Http\Controllers\Api\OltBrandController;

// Rutas de autenticación
Route::post('login', [AuthController::class, 'login']);
Route::post('refresh', [AuthController::class, 'refresh']); // Movido fuera del middleware para permitir tokens expirados

// Rutas protegidas con middleware auth:api
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
    Route::post('/profile/password', [ProfileController::class, 'changePassword']);

    // Obtained Customers API
    Route::prefix('obtained-customers')->name('obtained-customers')->middleware('role:superadmin,main_provider')->group(function () {

        // Listar todos los obtained customers
        Route::get('/', [ObtainedCustomersController::class, 'index'])->name('index');

        // Iniciar la recolección de datos
        Route::post('/collect', [ObtainedCustomersController::class, 'collect'])->name('collect');

        // Mostrar detalles de un obtained customer específico
        Route::get('/{obtainedCustomer}', [ObtainedCustomersController::class, 'show'])->name('show');

        // Verificar existencia de cliente
        Route::post('/check-existence', [ObtainedCustomersController::class, 'checkExistence'])->name('checkExistence');

        // Crear un customer a partir de un obtained customer
        Route::post('/{obtainedCustomer}/store', [ObtainedCustomersController::class, 'storeFromObtained'])->name('storeFromObtained');

        // Mismo proceso masivo
        Route::post('/store-massive', [ObtainedCustomersController::class, 'storeMassiveFromObtained'])->name('storeMassiveFromObtained');


        // Opcional: estado y estado completo
        Route::get('/{obtainedCustomer}/status', [ObtainedCustomersController::class, 'status'])->name('status');
        Route::get('/{obtainedCustomer}/complete-status', [ObtainedCustomersController::class, 'completeStatus'])->name('completeStatus');

    });

    // Resource principal
    Route::resource('customers', CustomerController::class);

    Route::prefix('customers')->name('customers')->group(function () {
        Route::middleware('role:superadmin,main_provider')->group(function () {
            Route::post('collect', [CustomerController::class, 'collect'])->name('customers.collect');
            Route::post('/{customer}/activar', [CustomerController::class, 'activarCliente'])->name('customers.activar');
            Route::post('/{customer}/suspender', [CustomerController::class, 'suspenderCliente'])->name('customers.suspender');
            Route::post('/{customer}/change-speed', [CustomerController::class, 'changeSpeed'])->name('customers.changeSpeed');
        });

        Route::middleware('role:superadmin,main_provider,isp_representative')->group(function () {
            Route::get('/{customer}/status', [CustomerController::class, 'status'])->name('customers.status');
            Route::get('/{customer}/speed', [CustomerController::class, 'getSpeed'])->name('customers.getSpeed');
            Route::get('/{customer}/complete-status', [CustomerController::class, 'completeStatus'])->name('completeStatus');
        });
    });


    // Rutas principales para OLT (API Resource)
    Route::apiResource('olts', OLTController::class)->middleware('role:superadmin,main_provider');

    // Rutas adicionales específicas
    Route::prefix('olts')->name('olts')->group(function () {
        // Actualizar relaciones OLT ↔ ISPs
        Route::post('/{olt}/update-relations', [OLTController::class, 'updateRelations'])
        ->name('olts.updateRelations');

        // Obtener relaciones detalladas de un OLT
        Route::get('/{olt}/relations', [OLTController::class, 'getRelations'])
        ->name('olts.getRelations');

        // Obtener modelos según la marca
        Route::get('/brand/{brandId}/models', [OLTController::class, 'getModelsByBrand'])
        ->name('olts.getModelsByBrand');

        // Obtener estructura de interfaces GPON de un OLT
        Route::get('/{olt}/gpon-structure', [OLTController::class, 'getGponInterfaceStructure'])
        ->name('olts.getGponInterfaceStructure');

        Route::post('/{olt}/activate', [OLTController::class, 'activate']);
        Route::post('/{olt}/deactivate', [OLTController::class, 'deactivate']);   
    });

    // Resource principal para usuarios
    Route::resource('users', UsersController::class)->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middleware('role:superadmin,main_provider');

    // Rutas específicas para API de usuarios
    Route::prefix('users')->name('users.')->middleware('role:superadmin,main_provider')->group(function () {

        // Ejemplo de rutas extra que podrían ser útiles
        Route::post('/{user}/activate', [UsersController::class, 'activate'])->name('activate');
        Route::post('/{user}/deactivate', [UsersController::class, 'deactivate'])->name('deactivate');
        Route::get('/{user}/details', [UsersController::class, 'show'])->name('details');
        Route::post('/{user}/activate', [UsersController::class, 'activate']);
        Route::post('/{user}/deactivate', [UsersController::class, 'deactivate']);
        Route::put('/{user}/change-password', [UsersController::class, 'updatePassword']);
    });

    // Rutas REST principales
    Route::resource('isps', ISPController::class)
    ->only(['index', 'store', 'show', 'update', 'destroy'])
    ->middleware('role:superadmin,main_provider');

    // Rutas personalizadas del recurso ISP
    Route::prefix('isps')->name('isps.')->middleware('role:superadmin,main_provider')->group(function () {
        Route::post('/{isp}/activate', [ISPController::class, 'activate']);
        Route::post('/{isp}/deactivate', [ISPController::class, 'deactivate']);    
    });

    // OLT Brands
    Route::resource('/olt-brands', OltBrandController::class)->middleware('role:superadmin,main_provider');
});
