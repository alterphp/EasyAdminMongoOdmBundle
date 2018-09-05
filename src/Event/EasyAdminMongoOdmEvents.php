<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Event;

final class EasyAdminMongoOdmEvents
{
    // Events related to initialization
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const PRE_INITIALIZE = 'easy_admin_mongo_odm.pre_initialize';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_INITIALIZE = 'easy_admin_mongo_odm.post_initialize';

    // Events related to backend views
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const PRE_DELETE = 'easy_admin_mongo_odm.pre_delete';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_DELETE = 'easy_admin_mongo_odm.post_delete';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const PRE_EDIT = 'easy_admin_mongo_odm.pre_edit';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_EDIT = 'easy_admin_mongo_odm.post_edit';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const PRE_LIST = 'easy_admin_mongo_odm.pre_list';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_LIST = 'easy_admin_mongo_odm.post_list';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const PRE_NEW = 'easy_admin_mongo_odm.pre_new';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_NEW = 'easy_admin_mongo_odm.post_new';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const PRE_SEARCH = 'easy_admin_mongo_odm.pre_search';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_SEARCH = 'easy_admin_mongo_odm.post_search';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const PRE_SHOW = 'easy_admin_mongo_odm.pre_show';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_SHOW = 'easy_admin_mongo_odm.post_show';

    // Events related to Doctrine entities
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const PRE_PERSIST = 'easy_admin_mongo_odm.pre_persist';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_PERSIST = 'easy_admin_mongo_odm.post_persist';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const PRE_UPDATE = 'easy_admin_mongo_odm.pre_update';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_UPDATE = 'easy_admin_mongo_odm.post_update';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const PRE_REMOVE = 'easy_admin_mongo_odm.pre_remove';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_REMOVE = 'easy_admin_mongo_odm.post_remove';

    // Events related to Doctrine Query Builder usage
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_LIST_QUERY_BUILDER = 'easy_admin_mongo_odm.post_list_query_builder';
    /** @Event("Symfony\Component\EventDispatcher\GenericEvent") */
    const POST_SEARCH_QUERY_BUILDER = 'easy_admin_mongo_odm.post_search_query_builder';
}
