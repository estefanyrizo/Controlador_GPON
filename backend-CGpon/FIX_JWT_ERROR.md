# Fix JWT Configuration Error

## 🐛 Problema

Error al intentar hacer login:
```
Carbon\Carbon::rawAddUnit(): Argument #3 ($value) must be of type int|float, string given
```

## ✅ Solución Aplicada

He actualizado el archivo `config/jwt.php` para forzar la conversión a enteros:

```php
'ttl' => (int) env('JWT_TTL', 60),
'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 20160),
```

## 📝 Verificar tu archivo .env

Asegúrate de que tu archivo `.env` tenga estos valores configurados correctamente:

```env
JWT_SECRET=tu_secret_key_aqui
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_BLACKLIST_ENABLED=true
JWT_BLACKLIST_GRACE_PERIOD=0
```

**IMPORTANTE:** Los valores deben ser números sin comillas.

### ❌ Incorrecto
```env
JWT_TTL="60"
JWT_REFRESH_TTL="20160"
```

### ✅ Correcto
```env
JWT_TTL=60
JWT_REFRESH_TTL=20160
```

## 🔧 Comandos Ejecutados

Ya ejecuté el comando para limpiar la caché:
```bash
php artisan config:clear
```

## 🧪 Probar el Login

Ahora puedes intentar hacer login nuevamente. El error debería estar resuelto.

## 📊 Valores Recomendados

- **JWT_TTL**: 60 (minutos) - Tiempo de vida del token
- **JWT_REFRESH_TTL**: 20160 (minutos = 14 días) - Tiempo para poder refrescar el token

### Para Desarrollo (Opcional)
Si quieres probar el sistema de expiración más rápido durante desarrollo:
```env
JWT_TTL=1
```
Esto hará que el token expire en 1 minuto.

### Para Producción
```env
JWT_TTL=60
```
Token expira en 1 hora (recomendado).

## 🔍 Si el Error Persiste

1. **Verificar que no haya espacios extra en .env**:
   ```env
   JWT_TTL=60  ← Correcto
   JWT_TTL = 60  ← Incorrecto (espacios alrededor del =)
   ```

2. **Regenerar el JWT Secret si no existe**:
   ```bash
   php artisan jwt:secret
   ```

3. **Limpiar todas las cachés**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

4. **Reiniciar el servidor de desarrollo**:
   ```bash
   # Detener el servidor (Ctrl+C)
   # Volver a iniciarlo
   php artisan serve
   ```

## 📚 Explicación Técnica

El problema ocurría porque:
1. La función `env()` en Laravel devuelve strings por defecto
2. Carbon (librería de fechas) espera int/float para sumar minutos
3. Al pasar un string, Carbon lanzaba un TypeError

La solución fue agregar `(int)` para forzar la conversión a entero en el archivo de configuración.

---

**Fecha:** Noviembre 2025  
**Estado:** ✅ Resuelto

