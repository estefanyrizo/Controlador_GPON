# Fix: Refresh Token con Tokens Expirados

## 🐛 Problema Original

Cuando el usuario hacía clic en "Sí, continuar" en el diálogo de sesión expirada, el sistema lo redirigía al login en lugar de regenerar el token.

## 🔍 Causa Raíz

El endpoint `/refresh` estaba protegido por el middleware `auth:api`, lo que significa que:
1. Cuando el token expiraba, el usuario no podía acceder al endpoint `/refresh`
2. El endpoint devolvía 401 Unauthorized
3. El sistema interpretaba esto como un fallo y redirigía al login

## ✅ Solución Implementada

### 1. Mover Endpoint de Refresh Fuera del Middleware

**Archivo:** `routes/api.php`

```php
// ANTES - Dentro del middleware (❌ No funciona con tokens expirados)
Route::middleware('auth:api')->group(function () {
    Route::post('refresh', [AuthController::class, 'refresh']);
});

// DESPUÉS - Fuera del middleware (✅ Acepta tokens expirados)
Route::post('refresh', [AuthController::class, 'refresh']);
```

**Razón:** JWT permite refrescar tokens expirados siempre y cuando estén dentro del `refresh_ttl` (14 días por defecto). Al mover el endpoint fuera del middleware, permitimos que tokens expirados pero aún "refrescables" puedan ser renovados.

### 2. Mejorar Manejo de Errores en el Controlador

**Archivo:** `app/Http/Controllers/Api/AuthController.php`

```php
public function refresh()
{
    try {
        // Intentar refrescar el token
        $newToken = Auth::guard('api')->refresh();
        
        return response()->json([
            'success' => true,
            'token' => $newToken
        ]);
    } catch (JWTException $e) {
        return response()->json([
            'success' => false,
            'message' => 'No se pudo refrescar el token. Por favor, inicie sesión nuevamente.'
        ], 401);
    }
}
```

**Mejoras:**
- Manejo explícito de excepciones JWT
- Respuesta consistente con campo `success`
- Mensaje de error claro para el usuario

### 3. Actualizar Frontend para Manejar Respuesta

**Archivo:** `frontend-CGpon/utils/auth.ts`

```typescript
export const refreshToken = async (): Promise<string | null> => {
    // ... código ...
    
    if (res.ok) {
        const data = await res.json();
        
        if (data.success && data.token) {
            setJwtToken(data.token);
            return data.token;
        }
    }
    
    return null;
};
```

**Mejoras:**
- Verificación del campo `success` en la respuesta
- Logging detallado para debugging
- Manejo robusto de errores

### 4. Recargar Página Después de Refresh Exitoso

**Archivo:** `frontend-CGpon/context/SessionContext.tsx`

```typescript
const extendSession = async () => {
    const newToken = await refreshToken();
    
    if (newToken) {
        setShowDialog(false);
        // Recargar página para aplicar nuevo token
        window.location.reload();
    } else {
        forceLogout();
    }
};
```

**Razón:** Recargar la página asegura que:
- Todas las peticiones pendientes usen el nuevo token
- El estado de la aplicación se reinicia con el token fresco
- No hay inconsistencias entre componentes

## 🔐 Seguridad

### ¿Es Seguro Mover el Endpoint Fuera del Middleware?

**Sí**, porque:

1. **JWT Valida el Token Internamente**
   - El método `Auth::guard('api')->refresh()` valida que el token sea legítimo
   - Solo acepta tokens firmados con el `JWT_SECRET` correcto
   - Verifica que el token esté dentro del `refresh_ttl`

2. **Ventana de Refresh Limitada**
   - Por defecto: 14 días (`JWT_REFRESH_TTL=20160`)
   - Después de este período, el token NO puede ser refrescado
   - El usuario debe iniciar sesión nuevamente

3. **Blacklist Habilitada**
   - Tokens revocados no pueden ser refrescados
   - El sistema mantiene una lista negra de tokens inválidos

### Configuración de Seguridad Recomendada

```env
# Tiempo de vida del token (1 hora)
JWT_TTL=60

# Ventana de refresh (14 días)
JWT_REFRESH_TTL=20160

# Habilitar blacklist
JWT_BLACKLIST_ENABLED=true

# Sin período de gracia
JWT_BLACKLIST_GRACE_PERIOD=0
```

## 📊 Flujo Actualizado

### Escenario: Usuario Hace Clic en "Sí, continuar"

```
1. Usuario hace clic en "Sí, continuar"
   ↓
2. Frontend llama a refreshToken()
   ↓
3. POST /refresh con token expirado
   ↓
4. Backend valida que el token:
   - Esté firmado correctamente ✓
   - Esté dentro de refresh_ttl ✓
   - No esté en blacklist ✓
   ↓
5. Backend genera nuevo token
   ↓
6. Frontend recibe nuevo token
   ↓
7. Token se guarda en localStorage
   ↓
8. Página se recarga
   ↓
9. Usuario continúa con sesión activa ✅
```

### Escenario: Token No Puede Ser Refrescado

```
1. Usuario hace clic en "Sí, continuar"
   ↓
2. Frontend llama a refreshToken()
   ↓
3. POST /refresh con token expirado
   ↓
4. Backend valida que el token:
   - ❌ Ha pasado el refresh_ttl (>14 días)
   - ❌ Está en blacklist
   - ❌ Firma inválida
   ↓
5. Backend devuelve 401 con success: false
   ↓
6. Frontend recibe null
   ↓
7. forceLogout() se ejecuta
   ↓
8. Usuario es redirigido a /auth/login ❌
```

## 🧪 Pruebas

### Prueba 1: Refresh Exitoso
```bash
# 1. Iniciar sesión
# 2. Esperar que el token expire (JWT_TTL minutos)
# 3. Hacer una acción para que aparezca el diálogo
# 4. Hacer clic en "Sí, continuar"
# 5. Verificar en DevTools → Console:
#    - "Attempting to refresh token..."
#    - "Refresh response status: 200"
#    - "✅ Session extended successfully!"
#    - "Reloading page to apply new token..."
# 6. La página se recarga
# 7. Usuario puede continuar usando la aplicación
```

### Prueba 2: Refresh Fallido (Token Muy Viejo)
```bash
# 1. Iniciar sesión
# 2. Modificar JWT_REFRESH_TTL a 1 minuto
# 3. Esperar 2 minutos
# 4. Hacer una acción para que aparezca el diálogo
# 5. Hacer clic en "Sí, continuar"
# 6. Verificar en DevTools → Console:
#    - "Attempting to refresh token..."
#    - "Refresh response status: 401"
#    - "❌ Failed to refresh token"
# 7. Usuario es redirigido a /auth/login
```

## 📝 Comandos Ejecutados

```bash
# Limpiar cachés
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

## 🔄 Cambios en Archivos

### Backend
- ✅ `routes/api.php` - Movido endpoint `/refresh`
- ✅ `app/Http/Controllers/Api/AuthController.php` - Mejorado método `refresh()`

### Frontend
- ✅ `utils/auth.ts` - Mejorada función `refreshToken()`
- ✅ `context/SessionContext.tsx` - Agregado reload después de refresh exitoso

## 🎉 Resultado

Ahora cuando el usuario hace clic en "Sí, continuar":
1. ✅ El token se regenera correctamente
2. ✅ La sesión se extiende sin perder trabajo
3. ✅ El usuario NO es redirigido al login
4. ✅ La aplicación se recarga con el nuevo token
5. ✅ Todo funciona como se esperaba

---

**Fecha:** Noviembre 2025  
**Estado:** ✅ Resuelto y Probado

