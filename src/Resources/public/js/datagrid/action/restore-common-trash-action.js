define([
  'underscore',
  'oro/translator',
  'oro/datagrid/delete-action',
  'pim/router',
  'pim/security-context',
  'pim/dialog',
  'oro/mediator',
  'pim/fetcher-registry',
  'pim/user-context',
  'oro/messenger'
], function (
  _,
  __,
  DeleteAction,
  Router,
  SecurityContext,
  Dialog,
  mediator,
  FetcherRegistry,
  userContext,
  messenger
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
      let routeParams = {
        resource: this.resource,
        id: this.model.get('id'),
      };

      return Router.generate(this.route, routeParams);
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

    getConfirmDialog: function () {
      const entityCode = this.getEntityCode();
      this.confirmModal = Dialog.confirm(
        __(`ktpl.akeneo_trash.entity.${entityCode}.restore.confirm`),
        __('ktpl.akeneo_trash.entity.confirm_restore'),
        this.doDelete.bind(this),
        this.getEntityHint(true)
      );

      return this.confirmModal;
    },

    /**
     * Confirm delete item
     */
    doDelete: function () {
      this.model.id = true;
      this.model.destroy({
        url: this.getLink(),
        wait: true,
        error: function (element, response) {
          let contentType = response.getResponseHeader('content-type');
          let message = '';
          //Need to check if it is a json because the backend can return an error
          if (contentType.indexOf('application/json') !== -1) {
            const decodedResponse = JSON.parse(response.responseText);
            if (undefined !== decodedResponse.message) {
              message = decodedResponse.message;
            }
          }

          this.showErrorFlashMessage(message);
        }.bind(this),
        success: function () {
          var messageText = __('ktpl.akeneo_trash.entity.' + this.getEntityCode() + '.flash.restore.success');
          messenger.notify('success', messageText);
          userContext.initialize();

          mediator.trigger('datagrid:doRefresh:' + this.gridName);
        }.bind(this),
      });
    },

    /**
     * Get view for error modal
     *
     * @return {oro.Modal}
     */
    showErrorFlashMessage: function (response) {
      let message = '';

      if (typeof response === 'string') {
        message = response;
      } else {
        try {
          message = JSON.parse(response).message;
        } catch (e) {
          message = __('ktpl.akeneo_trash.entity.' + this.getEntityHint() + '.flash.restore.fail');
        }
      }

      messenger.notify('error', '' === message ? __('ktpl.akeneo_trash.entity.' + this.getEntityHint() + '.flash.restore.fail') : message);
    },
  });
});
