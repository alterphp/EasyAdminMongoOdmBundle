# EasyAdminMongoOdmBundle

Provides support of Doctrine Mongo ODM documents in EasyAdmin

This bundle is under development and keeps experimental as long as no v1.0.0 tag is available !

## Installation

EasyAdminMongoOdmBundle is installable aside an EasyAdmin configuration. It actually requires it !

`composer require alterphp/easyadmin-mongo-odm-bundle:dev-master`

## Configuration

Simple example :

```yaml
easy_admin_mongo_odm:
    documents:
        AnyDocument:
          class: App\Document\AnyDocument
        SomeDocument:
            class: App\Document\SomeDocument
            list:
                sort: createdAt
                fields:
                    - field1
                    - field2
                    - ...

# You can define menu for documents into easyadmin configuration
easy_admin:
    design:
        menu:
            - { label: AnyDocument, route: easyadmin_mongo_odm, params: { document: AnyDocument } }
            - { label: SomeDocument, route: easyadmin_mongo_odm, params: { document: SomeDocument } }
```

## Dev notes

* TwigPathPass compiler pass makes @EasyAdminMongoOdm templates to be searched in EasyAdmin bundle if not found in EasyAdminMongoOdm bundle.

## TODOs

* Show view for fields of type `hash`
* Controller listener to implement (and override controller by configuration)
* Exception listener for production env ?
* QueryBuilder => deal with associations ?
* PropertyConfigPass is not implemented => item `format` per field is not preset
* Menu items of type `document`

## Development tags

__USE_MAIN_CONFIG__ : Some backend configuration used from EasyAdmin bundle (when not specific to ODM).
__RESTRICTED_ACTIONS__ : Marks code lines that deal with actions limitation (new, edit and delete are not available for now).
__NO_ASSOCIATION__ : Disabled association mapping (Mongo ODM has `reference` feature, but it's not implemented here yet).
__SORT_ONLY_INDEXES__ : By default, only indexed fields are sortable (for performance reason).
