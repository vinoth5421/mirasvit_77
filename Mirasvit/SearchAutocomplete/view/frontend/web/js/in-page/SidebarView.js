/*eslint-disable */

function _extends() { _extends = Object.assign || function (target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i]; for (var key in source) { if (Object.prototype.hasOwnProperty.call(source, key)) { target[key] = source[key]; } } } return target; }; return _extends.apply(this, arguments); }

define(["underscore", "knockout", "jquery"], function (_underscore, _knockout, _jquery) {
  var SidebarView = function SidebarView(props) {
    "use strict";

    var _this = this;

    this.setBuckets = function (indexes, indexIdentifier) {
      var buckets = [];
      var activeBuckets = [];

      _underscore.each(indexes, function (idx) {
        if (idx.identifier != indexIdentifier) {
          return;
        }

        _underscore.each(idx.buckets, function (bucket) {
          var bucketItems = [],
              activeBucketItems = [];

          _underscore.each(bucket.buckets, function (item) {
            var state = _this.props.filterList().has(bucket.code) && _this.props.filterList().get(bucket.code).indexOf(item.key) >= 0;

            if (state) {
              activeBucketItems.push(_extends({}, item, {
                isActive: state,
                select: function select() {
                  return _this.selectItem(bucket, item);
                }
              }));
            }

            bucketItems.push(_extends({}, item, {
              isActive: state,
              select: function select() {
                return _this.selectItem(bucket, item);
              }
            }));
          });

          if (bucketItems.length > 0) {
            buckets.push(_extends({}, bucket, {
              buckets: bucketItems
            }));
          }

          if (activeBucketItems.length > 0) {
            activeBuckets.push(_extends({}, bucket, {
              buckets: activeBucketItems
            }));
          }
        });
      });

      _this.buckets(buckets);

      _this.activeBuckets(activeBuckets);
    };

    this.selectItem = function (bucket, item) {
      var map = _this.props.filterList();

      if (map.has(bucket.code)) {
        var filters = map.get(bucket.code);

        if (map.get(bucket.code).indexOf(item.key) >= 0) {
          filters.splice([map.get(bucket.code).indexOf(item.key)], 1);

          if (filters.length > 0) {
            map.set(bucket.code, filters);
          } else {
            map.delete(bucket.code);
          }
        } else {
          filters.push(item.key);
          map.set(bucket.code, filters);
        }
      } else {
        map.set(bucket.code, [item.key]);
      }

      _this.props.filterList(map);
    };

    this.props = props;
    this.buckets = _knockout.observableArray([]);
    this.activeBuckets = _knockout.observableArray([]);
    this.setBuckets(props.result().indexes, props.activeIndex());
    (0, _jquery)(document).click(".mstInPage__bucket .filter-options-title", function (e) {
      (0, _jquery)(e.target).closest(".mstInPage__bucket").toggleClass("active");
    });
    props.result.subscribe(function (result) {
      return _this.setBuckets(result.indexes, props.activeIndex());
    });
    props.activeIndex.subscribe(function (index) {
      return _this.setBuckets(props.result().indexes, index);
    });
  };

  return {
    SidebarView: SidebarView
  };
});
//# sourceMappingURL=SidebarView.js.map