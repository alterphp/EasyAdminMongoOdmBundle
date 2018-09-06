# EasyAdminMongoOdmBundle

Provides support of Doctrine Mongo ODM documents in EasyAdmin

This bundle is under development and keeps experimental as long as no v1.0.0 tag is available !

## Dev notes

* TwigPathPass compiler pass makes @EasyAdminMongoOdm templates to be searched in EasyAdmin bundle if not found in EasyAdminMongoOdm bundle.

## TODOs

* Controller listener to implement (and override controller by configuration)
* Exception listener for production env ?
* QueryBuilder => deal with associations ?
* PropertyConfigPass is not implemented => item `format` per field is not preset
* Menu items of type `document`

## Development tags

__USE_MAIN_CONFIG__ : Some backend configuration used from EasyAdmin bundle (when not specific to ODM)
__RESTRICTED_ACTIONS__ : Indicates code that deals with actions limitation (new, edit and delete are not available for now)
__NO_ASSOCIATION__ : Disabled association mapping
__SORT_ONLY_INDEXES__ : By default, only indexed fields are sortable.
