# Transbank Virtuemart 3.x Webpay Plugin

## Descripción

Este plugin de Virtuemart 3.x implementa el [SDK PHP de Webpay](https://github.com/TransbankDevelopers/transbank-sdk-php) en modalidad checkout. 

## Dependencias

* transbank/transbank-sdk

## Nota  
- La versión del sdk de php se encuentra en el archivo `config.sh`

## Preparar el proyecto para bajar dependencias

    ./config.sh

## Crear una versión del plugin empaquetado 

    ./package.sh

## Instalación del plugin para un comercio

El manual de instalación para el usuario final se encuentra disponible [acá](docs/INSTALLATION.md) o en PDF [acá](https://github.com/TransbankDevelopers/transbank-plugin-virtuemart-webpay/raw/master/docs/INSTALLATION.pdf
)

## Desarrollo

Para apoyar el levantamiento rápido de un ambiente de desarrollo, hemos creado la especificación de contenedores a través de Docker Compose.

Para usarlo seguir el siguiente [README Virtuemart 3.x](./docker-virtuemart3)

## Generar una nueva versión

Para generar una nueva versión, se debe crear un PR (con un título "Prepare release X.Y.Z" con los valores que correspondan para `X`, `Y` y `Z`). Se debe seguir el estándar semver para determinar si se incrementa el valor de `X` (si hay cambios no retrocompatibles), `Y` (para mejoras retrocompatibles) o `Z` (si sólo hubo correcciones a bugs).

En ese PR deben incluirse los siguientes cambios:

1. Modificar el archivo CHANGELOG.md para incluir una nueva entrada (al comienzo) para `X.Y.Z` que explique en español los cambios.

Luego de obtener aprobación del pull request, debes mezclar a master e inmediatamente generar un release en GitHub con el tag `vX.Y.Z`. En la descripción del release debes poner lo mismo que agregaste al changelog.

Con eso Travis CI generará automáticamente una nueva versión del plugin y actualizará el Release de Github con el zip del plugin.
