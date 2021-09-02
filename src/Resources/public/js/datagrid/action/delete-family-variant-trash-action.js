define(['oro/datagrid/delete-common-trash-action'], function (
    DeleteAction
  ) {
    return DeleteAction.extend({
        route: 'ktpl_akeneo_trash_family_variant_rest_remove',
        resource: 'family_variant',
        acl: 'ktpl_akeneo_trash_remove_family_variant',
    });
  });
  