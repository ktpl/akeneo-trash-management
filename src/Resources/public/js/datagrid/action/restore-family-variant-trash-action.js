define([
    'oro/datagrid/delete-restore-common-trash-action'
  ], function (
    DeleteAction,
  ) {
    return DeleteAction.extend({
       route: 'ktpl_akeneo_trash_rest_restore',
       resource: 'family_variant',
       acl: 'ktpl_akeneo_trash_restore_trash',
    });
  });
  