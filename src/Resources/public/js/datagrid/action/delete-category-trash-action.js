define(['oro/datagrid/delete-common-trash-action'], function (
    DeleteAction
  ) {
    return DeleteAction.extend({
        route: 'ktpl_akeneo_trash_category_rest_remove',
        resource: 'category',
        acl: 'ktpl_akeneo_trash_remove_category',
    });
  });
  