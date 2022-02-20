## S3UploadSDK plugin para CakePHP

Este plugin permite rapidamente hacer que una Table suba archivos a un bucket de AWS S3, mediante el behavior proporcionado.


----------

## Changelog

Fecha: 2020-11-24

* Arreglado alerta de deprecated en behavior

Fecha: 2019-03-25

* Arreglado bug que hacia que los archivos se subieran a S3 como texto plano

Fecha: 2018-11-20

* Agregada funcionalidad para eliminar archivos cuando están en la misma tabla

Fecha: 2018-10-11

* Reescrita la documentación

----------

## Documentación

### Funcionalidades

**Implementadas:**

- Incluye un behavior para las tablas que deban subir archivos, S3UploadSDK, con las siguientes funcionalidades:
    + Sube archivos al bucket de s3 configurado
    + Almacena en la base de datos los campos de metadatos asi como también la URL completa del archivo.
    + Permite fácilmente configurar el archivo a subir como sólo imagen
    + Provee varios validadores preconfigurados para integrarlos rapidamente
    + Permite togglear para que los archivos se suban localmente
    + Método de utilidad para eliminar archivos de la tabla sin borrar el row completo.

**Pendientes:**

- Permitir configurar varios buckets a la vez

### Instalación

Para obtener el plugin con **composer** se requiere agregar a `composer.json` lo siguiente:

1. Al objeto `"require"`, agregar el plugin: `"ccasanovas/cake-s3upload": "dev-master"`
2. Al arreglo de `"repositories"` agregar el objeto: ```{"type": "vcs", "url": "git@bitbucket.org:ccasanovas/cake-s3upload.git"}```
3. correr `composer update`

NOTA: asegurarse de tener los permisos de acceso/deploy correctos en el repositorio.

Una vez instalado el plugin en el repositorio se puede agregar a las tablas necesarias el behavior:

```php
$this->loadBehavior('Ccasanovas/S3UploadSDK.S3UploadSDK', [
    /* Nombre del campo principal, donde se guardará el nombre del archivo: */
    'avatar_file_name' => [
        /* Estos campos se almacenarán en la base de datos: */
        'fields' => [
            /* campos requeridos: */
            'dir'  => 'avatar_file_dir', //el directorio del archivo
            'size' => 'avatar_file_size', //el tamaño del archivo en bytes
            'type' => 'avatar_file_type', //el mime type del archivo
            'url'  => 'avatar_url', //la dirección completa del archivo una vez subido

            /* campos opcionales usados para imagenes: */
            'image_width'  => 'avatar_width', //ancho de la imagen
            'image_height' => 'avatar_height' //alto de la imagen
        ],
        /*  si el campo se configura como images_only,
            se agregará validación para asegurar que solo se suban imagenes
        */
        'images_only' => true
    ]
]);
```

### Estructura

#### Ccasanovas\S3UploadSDK\Model\Behavior\AwsS3UploadBehavior

- *public* **initialize**(array $config)
    + Comprueba que las variables de configuracion necesarias estén disponibles y sean correctos
    + Construye y configura el cliente para interactuar con S3
    + Le agrega el adaptador de s3 y un path por defecto a todos los campos configurados en el modelo
    + Una vez procesadas las configuraciones de los campos, agrega el behavior 'Josegonzalez/Upload.Upload' a la Table y se las pasa
- *public* **buildValidator**(Event $event, Validator $validator, $name)
    + Para cada uno de los campos configurados, agrega reglas de validación estandar para el tipo de archivo
    + Incluye las siguientes reglas:
        * fileUnderPhpSizeLimit
        * fileUnderFormSizeLimit
        * fileCompletedUpload
        * fileFileUpload
        * fileSuccessfulWrite
    + Además, si el campo está configurado con `['images_only' => true]`, agrega validación para el mimeType, aceptando solo los siguientes:
        * image/gif
        * image/png
        * image/jpg
        * image/jpeg
- *public* **afterRules**(Event $event, EntityInterface $entity, ArrayObject $options, $result, $operation)
    + Si los campos de alto y ancho están configurados, intenta obtener esas propiedades del archivo y popular esos campos
- *public* **beforeSave**(Event $event, EntityInterface $entity, ArrayObject $options)
    + almancena la url completa del archivo subido a S3 en el campo de url
    + Si se está borrando el archivo, se encarga de limpiar los campos relacionados también (dir, type, size, etc)
- *public* **deleteFile**($id, $field)
    + Método de utilidad para borrar el $field de la entidad. Junto con el beforeSave, se puede llamar por ejemplo a `$this->Products->deleteFile($product_id, 'file_name');` y se borrará el contenido de `file_name` y sus campos relacionados (por ej: file_dir, file_size, file_type, etc).

### Variables de configuracion

Las variables de configuración se guardan en el arreglo de configuración de la aplicación al igual que el resto de las configuraciones (`config/app.php` por defecto).

Las configuraciones disponibles son:

```php
'AwsS3' => [
    'base_url' => 'https://s3.amazonaws.com',
    'key'      => '', //required.
    'secret'   => '', //required.
    'region'   => 'us-east-1', //required. region the bucket is it
    'bucket'   => 'test-bucket-name', //required. name of the bucket
    'prefix'   => 'path/to/test/prefix' //optional. Appends this path to all s3 object address.

    /*  Campo opcional. Por defecto false. se puede poner explicitamente
        como true para que no se use s3 y en su lugar se almacene localmente.
        Util para testing.
    */
    'local_only' => false,
]
```

### Testing

//TODO
