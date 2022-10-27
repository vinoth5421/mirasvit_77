/*eslint-disable */

function _extends() { _extends = Object.assign || function (target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i]; for (var key in source) { if (Object.prototype.hasOwnProperty.call(source, key)) { target[key] = source[key]; } } } return target; }; return _extends.apply(this, arguments); }

define(["underscore", "knockout"], function (_underscore, _knockout) {
  var PagingView = function PagingView(props) {
    "use strict";

    var _this = this;

    this.setPages = function (indexes, indexIdentifier) {
      var pages = [];

      _underscore.each(indexes, function (idx) {
        if (idx.identifier != indexIdentifier) {
          return;
        }

        var pageItems = _underscore.map(idx.pages, function (item) {
          return _extends({}, item, {
            select: function select() {
              return item.isActive ? '' : _this.selectItem(item);
            }
          });
        });

        idx.pages = pageItems;

        _this.pages(pageItems);
      });
    };

    this.selectItem = function (item) {
      _this.props.page(parseInt(item.label));
    };

    this.props = props;
    this.pages = _knockout.observableArray([]);
    this.setPages(props.result().indexes, props.activeIndex());
    props.result.subscribe(function (result) {
      return _this.setPages(result.indexes, props.activeIndex());
    });
    props.activeIndex.subscribe(function (index) {
      return _this.setPages(props.result().indexes, index);
    });
    props.page.subscribe(function (page) {
      return _this.setPages(props.result().indexes, props.activeIndex());
    });
  };

  return {
    PagingView: PagingView
  };
});
//# sourceMappingURL=PagingView.js.map