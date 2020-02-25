# Changelog
Todos los cambios notables a este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
y este proyecto adhiere a [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2019-02-25
### Added
- Agrega métricas de uso cuando se pasa a producción en la configuración del plugin [#16](https://github.com/TransbankDevelopers/transbank-plugin-virtuemart-webpay/pull/16)
- Agregar instrucciones de implementación de producción [#15](https://github.com/TransbankDevelopers/transbank-plugin-virtuemart-webpay/pull/15)
- Agregar plantilla de problemas y/o errores [#13](https://github.com/TransbankDevelopers/transbank-plugin-virtuemart-webpay/pull/13)
### Changed
- Actualizar la implementación de travis api_key [#14](https://github.com/TransbankDevelopers/transbank-plugin-virtuemart-webpay/pull/14)

## [2.0.9] - 2019-04-04
### Fixed
- Corrige despliegue de información en el detalle de la transacción realizada, ahora se visualiza toda la información

## [2.0.8] - 2019-01-17
### Changed
- Se elimina la condición de VCI == "TSY" || VCI == "" para evaluar la respuesta de getTransactionResult debido a que
esto podría traer problemas con transacciones usando tarjetas internacionales.

## [2.0.7] - 2018-12-27
### Added
- Agrega logs de transacciones para poder obtener los datos como token, orden de compra, etc.. necesarios para el proceso de certificación.

## [2.0.6] - 2018-12-21
### Fixed
- Corrige validación de certificados

## [2.0.5] - 2018-11-29
### Changed
- Se corrigen varios problemas internos del plugin para entregar una mejor experiencia en virtuemart con Webpay
- Se mejoran las validaciones internas del proceso de pago.
- Se mejora la creación del pdf de diagnóstico.
- Se elimina la comprobación de la extensión mcrypt dado que ya no es necesaria por el plugin.
- Ahora soporta php 7.2.1

## [2.0.4] - 2018-05-28
### Changed
- Se modifica certificado de servidor para ambiente de integracion.

## [2.0.3] - 2018-05-18
### Changed
- Se corrige SOAP para registrar versiones

## [2.0.2] - 2018-04-12
### Changed
- Se modifica certificado de servidor para ambiente de integracion.


## [2.0.1] - 2018-03-15
### Added
- Se agrega archivo "changelog" para mantener orden de cambios realizados plugin

### Modificado
- Se modifica validacion para  transacciones internacionales
