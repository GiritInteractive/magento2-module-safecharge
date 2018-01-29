/**
 * Girit Safecharge js component.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
define(
    [
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Paypal/js/action/set-payment-method',
        'Magento_Customer/js/customer-data'
    ],
    function (
        $,
        Component,
        additionalValidators,
        redirectOnSuccessAction,
        setPaymentMethodAction,
        customerData
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Girit_Safecharge/payment/safecharge',
                isCcFormShown: true,
                creditCardToken: '',
                creditCardSave: 0,
                creditCardOwner: ''
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'creditCardToken',
                        'creditCardSave',
                        'isCcFormShown',
                        'creditCardOwner'
                    ]);

                var savedCards = this.getCardTokens();
                if (savedCards.length > 0) {
                    this.creditCardToken(savedCards[0]['value']);
                }

                return this;
            },

            initCcNumberFormatting: function() {
                $('#' + this.getCode() + '_form_cc input[name="payment[cc_number]"]')
                    .bind('input', function (e) {
                        e.target.value = e.target.value.replace(/[^\dA-Z]/g, '').replace(/(.{4})/g, '$1 ').trim();
                    }
                );
            },

            initCcCvvFormatting: function() {
                $('#' + this.getCode() + '_form_cc input[name="payment[cc_cid]"]')
                    .bind('input', function (e) {
                            e.target.value = e.target.value.replace(/[^\dA-Z]/g, '').replace(/(.{4})/g, '$1 ').trim();
                        }
                    );
            },

            context: function () {
                return this;
            },

            isShowLegend: function () {
                return true;
            },

            getCode: function() {
                return 'safecharge';
            },

            isActive: function() {
                return true;
            },

            useVault: function () {
                var useVault =  window.checkoutConfig.payment[this.getCode()].useVault;
                this.creditCardSave(useVault ? 1 : 0);

                return useVault;
            },

            isCcDetectionEnabled: function () {
                return window.checkoutConfig.payment[this.getCode()].isCcDetectionEnabled;
            },

            getCssClass: function () {
                return this.isCcDetectionEnabled() ? 'field type detection' : 'field type required';
            },

            canSaveCard: function () {
                return window.checkoutConfig.payment[this.getCode()].canSaveCard;
            },

            getCardTokens: function () {
                var savedCards = window.checkoutConfig
                    .payment[this.getCode()]
                    .savedCards;

                return _.map(savedCards, function (value, key) {
                    return {
                        'value': key,
                        'label': value
                    };
                });
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'cc_token': this.creditCardToken(),
                        'cc_save': this.creditCardSave(),
                        'cc_cid': this.creditCardVerificationNumber(),
                        'cc_type': this.creditCardType(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        'cc_number': this.creditCardNumber(),
                        'cc_owner': this.creditCardOwner()
                    }
                };
            },

            savedCardSelected: function (token) {
                if (token === undefined) {
                    this.isCcFormShown(true);
                } else {
                    this.isCcFormShown(false);
                }
            },

            is3dSecureEnabled: function () {
                return window.checkoutConfig.payment[this.getCode()].is3dSecureEnabled;
            },

            getAuthenticateUrl: function () {
                return window.checkoutConfig.payment[this.getCode()].authenticateUrl;
            },

            useExternalSolution: function () {
                return window.checkoutConfig.payment[this.getCode()].externalSolution;
            },

            getRedirectUrl: function () {
                return window.checkoutConfig.payment[this.getCode()].redirectUrl;
            },

            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);

                    if (self.useExternalSolution()) {
                        this.selectPaymentMethod();
                        setPaymentMethodAction(this.messageContainer).done(
                            function () {
                                $('body').trigger('processStart');

                                $.ajax({
                                    url: self.getRedirectUrl(),
                                    cache: false
                                }).done(function(html) {
                                    if (html !== '') {
                                        window.location.replace(html);
                                    } else {
                                        window.location.reload();
                                    }
                                }).fail(function() {
                                    window.location.reload();
                                });

                                $('body').trigger('processStop');
                                customerData.invalidate(['cart']);
                            }.bind(this)
                        );

                        return true;
                    }

                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                        function () {
                            self.afterPlaceOrder();

                             if (self.is3dSecureEnabled()) {
                                $.ajax({
                                    url: self.getAuthenticateUrl(),
                                    cache: false
                                }).done(function(html) {
                                    if (html !== '') {
                                        $('body').append(html);
                                        $('#safecharge_authenticate').submit();
                                    } else if (self.redirectAfterPlaceOrder) {
                                        redirectOnSuccessAction.execute();
                                    }
                                });
                            } else if (self.redirectAfterPlaceOrder) {
                                redirectOnSuccessAction.execute();
                            }
                        }
                    );

                    return true;
                }

                return false;
            }
        });
    }
);