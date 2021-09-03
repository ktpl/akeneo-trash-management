/* global define */
define([
    'jquery',
    'underscore',
    'oro/translator',
    'routing',
    'oro/datagrid/mass-action',
    'pim/router',
    'oro/messenger',
    'oro/loading-mask',
    'pim/dialog',
  ], function ($, _, __, Routing, MassAction, router, messenger, LoadingMask, Dialog) {
    'use strict';
  
    /**
     * Mass restore action
     *
     * @export  oro/datagrid/mass-restore-action
     * @class   oro.datagrid.MassRestoreAction
     *
     * @extends oro.datagrid.MassAction
     */
    return MassAction.extend({
      /** @type {Object} */
      config: undefined,
  
      initialize: function (options) {
        this.config = __moduleConfig;
  
        MassAction.prototype.initialize.apply(this, arguments);
      },
  
      /**
       * Displays a confirm dialog and mass restore if action is confirmed.
       */
      execute: function () {
        this.getData().then(data => {
          this.getConfirmDialog(data);
        });
      },
  
      /**
       * Converts grid data into pqb filters and gathers job instance code, actions and items count.
       *
       * @return {Promise}
       */
      getData: function () {
        let actionParameters = this.getActionParameters();
        actionParameters.actionName = this.route_parameters['actionName'];
        actionParameters.gridName = this.route_parameters['gridName'];
        const query = `?${$.param(actionParameters)}`;
  
        return $.ajax({
          url: Routing.generate('ktpl_akeneo_trash_rest_get_filter') + query,
          method: 'POST',
        }).then(response => {
          return {
            filters: response.filters,
            jobInstanceCode: this.config.jobInstanceCode,
            actions: [this.route_parameters['actionName']],
            itemsCount: response.itemsCount,
          };
        });
      },
  
      /**
       * Get view for confirm modal.
       *
       * @param {Object} data
       *
       * @return {oro.Modal}
       */
      getConfirmDialog: function (data) {
        this.confirmModal = Dialog.confirm(
          __(this.config.confirmLabel),
          __('pim_common.confirm_deletion'),
          this.doMassRestore.bind(this, data),
          this.getEntityHint(true)
        );
  
        return this.confirmModal;
      },
  
      /**
       * Sends request to mass restore items.
       *
       * @param {Object} data
       */
      doMassRestore: function (data) {
        const loadingMask = new LoadingMask();
        loadingMask.render().$el.appendTo($('.hash-loading-mask')).show();
  
        $.ajax({
          method: 'POST',
          contentType: 'application/json',
          url: Routing.generate(this.config.route),
          data: JSON.stringify(data),
        })
          .then(() => {
            router.redirectToRoute(this.config.backRoute);
  
            const translatedAction = __('pim_datagrid.mass_action.mass_delete');
            messenger.notify(
              'success',
              __(this.config.launchedLabel, {
                operation: translatedAction,
              })
            );
          })
          .fail(() => {
            messenger.notify('error', __(this.config.launchErrorLabel));
          })
          .always(() => {
            loadingMask.hide().$el.remove();
          });
      },
    });
  });
  