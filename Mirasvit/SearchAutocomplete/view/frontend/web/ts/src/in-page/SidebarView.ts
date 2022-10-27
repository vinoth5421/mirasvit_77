import { Bucket, BucketItem, IndexResult, Result } from "../types"
import _ from "underscore"
import ko from "knockout"
import $ from "jquery"

interface Props {
    result: KnockoutObservable<Result>
    activeIndex: KnockoutObservable<string>
    filterList: KnockoutObservable<Map<string, string>>
}

interface SelectableBucketItem extends BucketItem {
    select: () => void
    isActive: boolean
}

interface SelectableBucket {
    code: string
    label: string
    buckets: SelectableBucketItem[]
    activeBuckets: SelectableBucketItem[]
}

export class SidebarView {
    props: Props

    buckets: KnockoutObservableArray<SelectableBucket>
    activeBuckets: KnockoutObservableArray<SelectableBucket>

    constructor(props: Props) {
        this.props = props
        this.buckets = ko.observableArray([])
        this.activeBuckets = ko.observableArray([])

        this.setBuckets(props.result().indexes, props.activeIndex())
        $(document).click(".mstInPage__bucket .filter-options-title", e => {
            $(e.target).closest(".mstInPage__bucket"). toggleClass("active")
        });

        props.result.subscribe(result => this.setBuckets(result.indexes, props.activeIndex()))
        props.activeIndex.subscribe(index => this.setBuckets(props.result().indexes, index))
    }

    setBuckets = (indexes: IndexResult[], indexIdentifier: string) => {
        let buckets: SelectableBucket[] = []
        let activeBuckets: SelectableBucket[] = []

        _.each(indexes, idx => {
            if (idx.identifier != indexIdentifier) {
                return
            }

            _.each(idx.buckets, bucket => {
                let bucketItems = [],
                    activeBucketItems = []

                _.each(bucket.buckets, item => {
                    let state = this.props.filterList().has(bucket.code) && this.props.filterList().get(bucket.code).indexOf(item.key) >=0

                    if (state) {
                        activeBucketItems.push({
                            ...item,
                            isActive: state,
                            select:   () => this.selectItem(bucket, item),
                        })
                    }

                    bucketItems.push({
                        ...item,
                        isActive: state,
                        select:   () => this.selectItem(bucket, item),
                    })
                })

                if (bucketItems.length > 0) {
                    buckets.push({
                        ...bucket,
                        buckets: bucketItems,
                    })
                }

                if (activeBucketItems.length > 0) {
                    activeBuckets.push({
                        ...bucket,
                        buckets: activeBucketItems,
                    })
                }
            })
        })

        this.buckets(buckets)
        this.activeBuckets(activeBuckets)
    }

    selectItem = (bucket: Bucket, item: BucketItem) => {
        const map = this.props.filterList()

        if (map.has(bucket.code)) {
            let filters = map.get(bucket.code)
            if (map.get(bucket.code).indexOf(item.key) >= 0) {
                filters.splice([map.get(bucket.code).indexOf(item.key)], 1)
                if (filters.length > 0) {
                    map.set(bucket.code, filters)
                } else {
                    map.delete(bucket.code)
                }
            } else {
                filters.push(item.key);
                map.set(bucket.code, filters)
            }
        } else {
            map.set(bucket.code, [item.key])
        }

        this.props.filterList(map)
    }
}
