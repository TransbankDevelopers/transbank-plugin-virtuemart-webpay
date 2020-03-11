[![GitHub tag (latest by date)](https://img.shields.io/github/v/tag/transbankdevelopers/transbank-plugin-virtuemart-webpay)](https://github.com/TransbankDevelopers/transbank-plugin-virtuemart-webpay/releases/latest)
[![GitHub](https://img.shields.io/github/license/transbankdevelopers/transbank-plugin-virtuemart-webpay)](LICENSE)
[![GitHub contributors](https://img.shields.io/github/contributors/transbankdevelopers/transbank-plugin-virtuemart-webpay)](https://github.com/TransbankDevelopers/transbank-plugin-virtuemart-webpay/graphs/contributors)
[![Build Status](https://travis-ci.org/TransbankDevelopers/transbank-plugin-virtuemart-webpay.svg?branch=master)](https://travis-ci.org/TransbankDevelopers/transbank-plugin-virtuemart-webpay)

# Transbank Virtuemart 3.x Webpay Plugin

Plugin **oficial** de Webpay para Virtuemart 3.x

## Descripción

Este plugin **oficial** de Transbank te permite integrar Webpay fácilmente en tu sitio VirtueMart. Está desarrollado en base al [SDK oficial de PHP](https://github.com/TransbankDevelopers/transbank-sdk-php)

### ¿Cómo instalar?
Puedes ver las instrucciones de instalación y su documentación completa en [transbankdevelopers.cl/plugin/virtuemart/](https://www.transbankdevelopers.cl/plugin/virtuemart/)
Adicionalmente, existe un manual de instalación para el usuario final [acá](docs/INSTALLATION.md) o en PDF [acá](https://github.com/TransbankDevelopers/transbank-plugin-virtuemart-webpay/raw/master/docs/INSTALLATION.pdf)


### Paso a producción
Al instalar el plugin, este vendrá configurado para funcionar en modo '**integración**'(en el ambiente de pruebas de Transbank). Para poder operar con dinero real (ambiente de **producción**), debes:

1. Tener tu propio código de comercio. Si no lo tienes, solicita Webpay Plus en [transbank.cl](https://publico.transbank.cl)
2. Luego de finalizar tu integración debes [generar tus credenciales](https://www.transbankdevelopers.cl/documentacion/como_empezar#credenciales-en-webpay)  (llave privada y llave pública) usando tu código de comercio. 
3. Enviar [esta planilla de integración](https://transbankdevelopers.cl/files/evidencia-integracion-webpay-plugins.docx) a soporte@transbank.cl, junto con la llave pública (generada en el paso anterior) y tu **logo (130x59 pixeles en formato GIF)**. 
4. Cuando Transbank confirme que ha cargado tu certificado público y logo, debes entrar a la pantalla de configuración del plugin dentro de Prestashop y colocar tu código de comercio, llave privada, llave pública y poner el ambiente de 'Producción'. 
5. Debes hacer una compra de $10 en el ambiente de producción para confirmar el correcto funcionamiento. 

Puedes ver más información sobre este proceso en [este link](https://www.transbankdevelopers.cl/documentacion/como_empezar#puesta-en-produccion).

# Desarrollo
A continuación, encontrarás información necesaria para el desarrollo de este plugin. 


## Dependencias

* transbank/transbank-sdk

## Nota  
- La versión del sdk de php se encuentra en el archivo `config.sh`

## Preparar el proyecto para bajar dependencias

    ./config.sh

## Crear una versión del plugin empaquetado 

    ./package.sh


## Ambiente de Desarrollo

Para apoyar el levantamiento rápido de un ambiente de desarrollo, hemos creado la especificación de contenedores a través de Docker Compose.

Para usarlo seguir el siguiente [README Virtuemart 3.x](./docker-virtuemart3)

## Generar una nueva versión

Para generar una nueva versión, se debe crear un PR (con un título "Prepare release X.Y.Z" con los valores que correspondan para `X`, `Y` y `Z`). Se debe seguir el estándar semver para determinar si se incrementa el valor de `X` (si hay cambios no retrocompatibles), `Y` (para mejoras retrocompatibles) o `Z` (si sólo hubo correcciones a bugs).

En ese PR deben incluirse los siguientes cambios:

1. Modificar el archivo CHANGELOG.md para incluir una nueva entrada (al comienzo) para `X.Y.Z` que explique en español los cambios.

Luego de obtener aprobación del pull request, debes mezclar a master e inmediatamente generar un release en GitHub con el tag `vX.Y.Z`. En la descripción del release debes poner lo mismo que agregaste al changelog.

Con eso Travis CI generará automáticamente una nueva versión del plugin y actualizará el Release de Github con el zip del plugin.
