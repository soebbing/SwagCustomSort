//{namespace name="backend/custom_sort/view/main"}
//{block name="backend/custom_sort/controller/main"}
Ext.define('Shopware.apps.CustomSort.controller.Main', {
    
    /**
     * Extend from the standard ExtJS 4
     * @string
     */
    extend: 'Ext.app.Controller',
    
    /**
     * Class property which holds the main application if it is created
     *
     * @default null
     * @object
     */
    mainWindow: null,

    refs: [
        { ref: 'articleView', selector: 'sort-articles-view' },
        { ref: 'articleList', selector: 'sort-articles-list' }
    ],

    /**
     * Sets up the ui component
     * @return void
     */
    init: function() {
        var me = this;

        me.subApplication.treeStore =  me.subApplication.getStore('Tree');
        me.subApplication.treeStore.load();

        me.subApplication.articleStore =  me.subApplication.getStore('Article');

        me.subApplication.categorySettings =  me.subApplication.getStore('Settings');

        me.control({
            'sort-category-tree': {
                itemclick: me.onItemClick
            },
            'sort-articles-view': {
                defaultSort: me.onSaveSettings,
                sortChange: me.onSortChange,
                categoryLink: me.onSaveSettings
            },
            'sort-articles-list': {
                moveToStart: me.onMoveToStart,
                moveToEnd: me.onMoveToEnd,
                moveToPrevPage: me.onMoveToPrevPage,
                moveToNextPage: me.onMoveToNextPage,
                articleMove: me.onArticleMove
            }
        });

        me.mainWindow = me.getView('main.Window').create({
            treeStore: me.subApplication.treeStore,
            articleStore: me.subApplication.articleStore,
            categorySettings: me.subApplication.categorySettings
        }).show();

        me.callParent(arguments);
    },

    onItemClick: function(view, record) {
        var me = this,
            grid = me.getArticleView(),
            list = me.getArticleList();

        //Hide grid buttons on category select
        grid.setDisabled(true);
        list.setDisabled(true);

        me.subApplication.categorySettings.getProxy().extraParams = { categoryId: record.get("id") };
        me.subApplication.categorySettings.load({
            callback: function(records, operation, success) {
                if (success) {
                    var record = records[0];
                    grid.loadRecord(record);
                }
            }
        });

        me.subApplication.articleStore.getProxy().extraParams = { categoryId: record.get("id") };
        me.subApplication.articleStore.load({
            callback: function() {
                grid.setLoading(false);
                grid.setDisabled(false);
                list.setDisabled(false);
            }
        });
    },

    onSortChange: function(record) {
        var me = this,
            list = me.getArticleList();

        list.setLoading(true);

        me.subApplication.articleStore.getProxy().extraParams = { sortBy: record }
        me.subApplication.articleStore.load({
            callback: function() {
                list.setLoading(false);
            }
        });
    },

    onSaveSettings: function() {
        var me = this,
            grid = me.getArticleView(),
            list = me.getArticleList(),
            form = grid.getForm(),
            record = form.getRecord(),
            values = form.getValues();

        if (values.categoryLink > 0) {
            grid.defaultSort.setDisabled(true);
            grid.sorting.setDisabled(true);
            list.setDisabled(true);
        }

        record.set(values);

        record.save({
            success: function() {
                Shopware.Notification.createGrowlMessage('Success', 'Successfully applied changes');
            },
            failure: function() {
                Shopware.Notification.createGrowlMessage('Error','Some error appear');
            }
        });
    },

    onMoveToStart: function() {
        //TODO: move after product select
    },

    onMoveToEnd: function() {
        //TODO: move after product select
    },

    onMoveToPrevPage: function() {
        //TODO: move after product select
    },

    onMoveToNextPage: function() {
        //TODO: move after product select
    },

    onArticleMove: function(articleStore, draggedRecord, targetRecord) {
        var index;

        if (!articleStore instanceof Ext.data.Store
            || !draggedRecord instanceof Ext.data.Model
            || !targetRecord instanceof Ext.data.Model) {
            return false;
        }

        index = articleStore.indexOf(targetRecord);

        articleStore.remove(draggedRecord);
        articleStore.insert(index, draggedRecord);

        return true;
    }

});
//{/block}