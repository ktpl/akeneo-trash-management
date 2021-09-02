define(['oro/datagrid/delete-action', 'pim/router', 'pim/security-context'], function (
  DeleteAction,
  Router,
  SecurityContext
) {
  return DeleteAction.extend({
    
    /**
     * {@inheritdoc}
     */
    initialize() {
      this.launcherOptions.enabled = this.isEnabled();

      return DeleteAction.prototype.initialize.apply(this, arguments);
    },

    getLink() {
      const productType = this.model.get('document_type');
      const id = this.model.get('technical_id');

      return Router.generate('ktpl_akeneo_trash_' + productType + '_rest_remove', {id});
    },

    getEntityHint() {
      return this.model.get('document_type').replace('_', ' ');
    },

    /**
     * {@inheritdoc}
     */
    isEnabled() {
      const productType = this.model.get('document_type');

      return (
        SecurityContext.isGranted('ktpl_akeneo_trash_remove_product')
      );
    },
  });
});
