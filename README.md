# Óptica Chacarilla App – Instalación en cPanel (WHM)

Ruta recomendada: /home/chacarilla/public_html/app.opticachacarilla.com/  →  https://app.opticachacarilla.com/?e=opticachacarilla
Antes de publicar: cambia el PIN en config.php y, en el panel, el WhatsApp (viene 51999999999), dirección, teléfono, redes y fotos.

## Instalación con GitHub (recomendada)
1. `.cpanel.yml` ya apunta a `/home/chacarilla/public_html/app.opticachacarilla.com`.
2. En cPanel → **Git Version Control → Create**, pega la URL del repositorio en "Clone URL". Para un repo privado, primero agrega la clave SSH de cPanel como *Deploy key* en GitHub. Usa como ruta del repositorio `/home/chacarilla/repositories/optica-chacarilla-app`.
3. En **Manage → Pull or Deploy**, pulsa **Deploy HEAD Commit**. Se copian los archivos a `app.opticachacarilla.com`.
4. La primera vez, edita `config.php` en el servidor y cambia el PIN por uno de **al menos 8 caracteres** (con `1234` o un PIN corto, el panel no deja entrar). Los despliegues siguientes no lo tocan, y tampoco tocan tus datos.
5. Para cada actualización: **Update from Remote** y luego **Deploy HEAD Commit**.

## Requisitos
- Una cuenta cPanel con PHP 7.4 o superior (recomendado 8.1+). No necesita base de datos.
- SSL activo (AutoSSL en cPanel). La app se instala en el celular solo con HTTPS.

## Instalación (5 minutos)
1. En WHM crea la cuenta (o usa una existente) y entra a su cPanel.
2. Crea un subdominio, por ejemplo `tienda.tudominio.com`.
3. En el Administrador de archivos, entra a la carpeta del subdominio y sube `app-marca-blanca.zip`. Luego elige "Extract".
4. Edita `config.php` y cambia el PIN `1234` por uno propio de al menos 8 caracteres. Tras 5 intentos fallidos, el panel se bloquea 15 minutos para esa conexión.
5. Verifica que la carpeta `data/` tenga permisos 750 o 755. PHP debe poder escribir en ella.
6. Abre `https://tienda.tudominio.com`. En la primera visita se cargan los datos de ejemplo.
7. Entra a **Contacto → Acceso para la empresa**, usa tu PIN y reemplaza el logo, los datos, las redes, los productos y las promociones.

## Varias empresas (marca blanca)
- Desde el panel, usa **+ Empresa** para crearla y completa su *slug*.
- Cada empresa tiene su propio enlace: `https://tienda.tudominio.com/?e=slug-de-la-empresa`.
- Si quieres aislar a cada cliente, crea una cuenta cPanel por cliente desde WHM y copia la app en cada una, cada una con su propio PIN y dominio.

## Venderlo con WHMCS
- Crea un producto de hosting, por ejemplo "Tienda App – Plan Básico (20 productos / 10 promos)", asociado a un paquete de WHM.
- Al aprovisionar la cuenta, sube el zip con el Administrador de archivos. También puedes automatizarlo con un script post-creación.
- El límite de 20 productos y 10 promociones viene definido en `index.html` (MAX_PROD / MAX_PROMO) y en `api.php`. Puedes subirlo en planes superiores.

## Ciclo de venta
Catálogo / Promos → Carrito → Datos del cliente → Pedido registrado (número, stock descontado) →
el cliente envía el pedido por WhatsApp → la empresa cambia el estado en el panel
(Nuevo → Confirmado → Pagado → En camino → Entregado, o Cancelado, que devuelve el stock) →
el cliente ve el estado en "Mis pedidos".

## Respaldo
Todos los datos están en `data/db.json`. Inclúyelo en tus backups de cPanel/JetBackup.

## Novedades v2
- **Catálogo por Familia › Subfamilia**, con filtros desplegables por familia, subfamilia, tipo de lente y tratamiento.
- **Búsqueda rápida** (botón 🔍 arriba): busca sin importar tildes por nombre, código, familia, tipo de lente o tratamiento.
- **Importar productos desde Excel**: Panel → Productos → "⬆ Importar Excel". Usa `plantilla-productos.xlsx`. Modos: *Actualizar y agregar* (por código) o *Reemplazar todo*. Antes de aplicar muestra nuevos, actualizados y filas con error. También permite "⬇ Exportar catálogo".
- **Medios de pago configurables**: Panel → Empresa → Medios de pago. Billeteras (Yape/Plin: número y titular), transferencias (banco, tipo de cuenta, N° de cuenta, CCI de 20 dígitos y titular) u otros. El cliente los ve al pagar, en la confirmación y en Contacto.
- **Ubigeo obligatorio** en el pedido (departamento → provincia → distrito, 1 893 distritos), junto con nombre, teléfono y dirección. El servidor también lo valida.
- Los datos guardados con la versión anterior se migran solos (categoría → familia; medios de pago desde la lista anterior).

Licencias de terceros: SheetJS (`vendor/xlsx.full.min.js`, Apache-2.0) y datos de ubigeo del paquete npm `peru-ubigeo` (MIT).
