define([
    'jquery',
    'ko',
    'underscore',
    'mage/translate',
    'Mirasvit_Search/js/cache',
    'Magento_Swatches/js/swatch-renderer',
], function ($, ko, _, $t, cache) {

    var CategorySearch = function (input) {
        this.$input = $(input);
        this.loading = false;
        this.config = [];
        this.result = false
    };

    CategorySearch.prototype = {
        init: function (config) {
            this.config = _.defaults(config, this.defaults);

            this.doSearch = _.debounce(this._doSearch, this.config.delay);

            this.xhr = null;

            this.$input.on("input", function () {
                this.result = this.search();
            }.bind(this));

            if (this.$input.val().length >= this.config.minSearchLength) {
                $('.mst_categorySearch_totals').show();
            }

            if (window.history && window.history.pushState) {
                $(window).on('popstate', function() {
                    let url = new URL(window.location.href);
                    if (url.searchParams.has('q')) {
                        this.$input.val(url.searchParams.get('q'));
                        this.result = this.search();
                    } else {
                        if (this.$input.val().length > 0) {
                            this.$input.val('');
                            this.result = this.search();
                        }
                    }

                }.bind(this));
            }
        },

        search: function () {
            $('.mst_categorySearch_totals').hide();
            $('.mst_categorySearchLoader').show();
            if (this.xhr != null) {
                this.xhr.abort();
                this.xhr = null;
            }

            if (this.$input.val().length >= this.config.minSearchLength || this.$input.val().length == 0) {
                this.doSearch(this.$input.val());
            } else {
                $('.mst_categorySearchLoader').hide();
                $('.mst_categorySearch_totals').hide();
            }

            return true;
        },

        _doSearch: function (query) {
            let url = new URL(window.location.href),
            cachedData = cache.getData(query);

            if (cachedData) {
                return this.applyResults(cachedData, query);
            }

            url.searchParams.delete('q');

            this.xhr = $.ajax({

                url:      url,
                dataType: 'json',
                type:     'GET',
                data:     {
                    q:      query,
                },
                success:  function (data) {
                    cache.setData(query, data);
                    this.applyResults(data, query);
                }.bind(this).bind(query)
            });
        },

        applyResults: function (data, query)
        {
            let url = new URL(window.location.href);

            this.$input.attr('placeholder', $t('Search across '+ data['products_count'] +' products'));
            $('.mst_categorySearch_totals').text($t('found '+ data['products_count'] +' products'));
            if (this.config.minProductsQtyToDisplay > data['products_count']) {
                if (query.length == 0) {
                    $('.mst_categorySearch').hide()
                }
            }

            this.updateContent(data);

            if (query.length > 0) {
                url.searchParams.append('q', query);
                window.history.pushState('', '', url);
            } else {
                url.searchParams.delete('q', query);
                window.history.pushState('', '', url);
                $('.mst_categorySearch_totals').hide();
            }
        },

        updateContent: function (data) {
            this.leftnavUpdate(data['leftnav']);
            this.productsUpdate(data['products']);
            this.updateCategoryViewData(data['categoryViewData']);
            $('.mst_categorySearchLoader').hide();
            if (this.$input.val().length > 0 ) {
                $('.mst_categorySearch_totals').show();
            } else {
                $('.mst_categorySearch_totals').hide();
            }
        },

        leftnavUpdate: function (leftnav) {
            var navigation = '.sidebar.sidebar-main .block.filter, .block.filter, .block-content.filter-content';

            if (leftnav) {
                $(navigation).not(".mst-nav__horizontal-bar .block.filter").replaceWith(leftnav);
                $(navigation).not(".mst-nav__horizontal-bar .block.filter").trigger('contentUpdated');
                $.each($('.block.filter .swatch-attribute-options .swatch-option'), function (index, item) {
                    $(item).SwatchRendererTooltip();
                });
                $.each($('.block.filter .swatch-attribute-options .swatch-option.color'), function (index, item) {
                    item.style.background = $(item).attr('data-option-tooltip-value') + ' no-repeat center';
                });

            }
        },

        productsUpdate: function (products) {
            if (products) {
                $('.toolbar.toolbar-products').remove();
                $('.products-grid, .message.info.empty').replaceWith(products);

                // trigger events
                $('.products-grid').trigger('contentUpdated');
                $('.products-grid').applyBindings();

                setTimeout(function () {
                    // execute after swatches are loaded
                    $('.products-grid').trigger('amscroll_refresh');
                }, 500);

                if ($.fn.lazyload) {
                    // lazyload images for new content (Smartwave_Porto theme)
                    $('.products-grid' + ' .porto-lazyload').lazyload({
                        effect: 'fadeIn'
                    });
                }

                if ($('.lazyload').length && $('.lazyload').unveil !== undefined) {
                    $("img.lazyload").unveil(0, function () {
                        $(this).load(function () {
                            this.classList.remove("lazyload");
                        });
                    });
                }
            }
        },

        updateCategoryViewData: function (categoryViewData) {
            if (categoryViewData === '') {
                return
            }

            if ($(".category-view").length === 0) {
                $('<div class="category-view"></div>').insertAfter('.page.messages');
            } else {
                $(".category-view").replaceWith(categoryViewData);
            }
        }
    };

    return CategorySearch;
});
