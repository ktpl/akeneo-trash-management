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
      const id = this.model.get('id');

      return Router.generate(this.route, { id });
    },

    getEntityHint() {
      return this.resource;
    },

    /**
     * {@inheritdoc}
     */
    isEnabled() {
      return (
        SecurityContext.isGranted(this.acl)
      );
    },
  });
});
