Ext.define('KahanitWPFW.ux.form.field.RepetitiveField', {
	extend: 'Ext.form.FieldContainer',
	alias: 'widget.repetitivefield',

	rowItems: [],
	rowCounter: 0,
	fieldCols: 0,

	initComponent: function() {
		var me = this;

		me.rowItems = Ext.clone(me.items);

		var labelsRow = me.getLabelsRow();
		var repetitiveFieldRows = {
			xtype: 'fieldcontainer',
			action: 'repetitivefieldrows',
			layout: {
				type: 'vbox',
				align: 'stretch'
			},

			items: me.getRows(this.value)
		};
		var editRemoveRow = me.getEditRemoveRow();

		Ext.apply(me, {
			layout: 'form',

			items: [{
				xtype: 'fieldcontainer',
				layout: 'form',

				items: [labelsRow, repetitiveFieldRows, editRemoveRow]
			},
			{
				xtype: 'button',
				text: 'add',
				action: 'addrow'
			}]
		});

		me.callParent();
	},

	listeners: {
		render: function(field) {
			this.mon(this.down('button[action="addrow"]'), 'click', this.addRow, this);
		}
	},

	getLabelsRow: function() {
		var labelRow = {
			xtype: 'fieldcontainer',
			action: 'repetitivelabelrow',
			layout: 'hbox',

			items: [{
				xtype: 'fieldcontainer',
				layout: 'hbox',
				flex: 1,

				defaults: {
					flex: 1
				},

				items: []
			},
			{
				xtype: 'displayfield',
				flex: 0,
				width: 50
			}]
		};

		Ext.Array.each(this.rowItems, function(item, index) {
			labelRow.items[0].items[index] = {};

			labelRow.items[0].items[index].xtype = 'displayfield';
			labelRow.items[0].items[index].fieldCol = index;

			if (item.hasOwnProperty('fieldColLabel')) {
				labelRow.items[0].items[index].value = item.fieldColLabel;
			} else if (item.hasOwnProperty('fieldLabel')) {
				labelRow.items[0].items[index].value = item.fieldLabel;
			}

			if (item.hasOwnProperty('flex')) {
				labelRow.items[0].items[index].flex = item.flex;
			}

			if (item.hasOwnProperty('width')) {
				labelRow.items[0].items[index].width = item.width;
			}

			if (item.hasOwnProperty('margin')) {
				labelRow.items[0].items[index].margin = item.margin;
			}

			item.fieldLabel = '';
		}, this);

		return labelRow;
	},

	getFieldValue: function(field, values, rowNo) {
		switch (field.xtype) {
		case 'textfield':
		case 'textareafield':
		case 'combobox':
			if (Ext.isDefined(values[rowNo])) {
				return {
					value: values[rowNo]
				};
			}
			break;
		case 'checkboxfield':
		case 'radiofield':
			if (Ext.isDefined(rowNo)) {
				field.inputValue = rowNo.toString();
			}

			if (Ext.isDefined(field.inputValue)) {
				if (Ext.Array.contains(values, field.inputValue)) {
					return {
						checked: true
					};
				} else {
					return {
						checked: false
					};
				}
			} else {
				return {
					checked: false
				};
			}
			break;
		}
	},

	getNewRow: function(values, rowNo) {
		var rowItems = Ext.clone(this.rowItems)

		if (Ext.isDefined(values) && Ext.isObject(values)) {
			Ext.Array.each(rowItems, function(item, index) {
				if (Ext.isDefined(values[item.name.replace("[]", "")])) {
					Ext.apply(item, this.getFieldValue(item, values[item.name.replace("[]", "")], rowNo));
				}
			}, this);
		}

		var row = {
			xtype: 'fieldcontainer',
			action: 'repetitivefieldrow',
			layout: 'hbox',

			items: [{
				xtype: 'fieldcontainer',
				layout: 'hbox',
				flex: 1,

				defaults: {
					flex: 1
				},

				items: rowItems
			},
			{
				xtype: 'button',
				text: 'delete',
				action: 'deleterow',
				flex: 0,
				width: 50,
				handler: function() {
					this.up().up().remove(this.up());
				}
			}]
		}

		this.rowCounter++;

		return row;
	},

	getRows: function(value) {
		var rows = [];

		if (Ext.isDefined(value) && Ext.isObject(value)) {
			var rowNum = 0;
			var values = {};

			Ext.Array.each(this.rowItems, function(item, index) {
				if (Ext.isDefined(item.name)) {
					if (Ext.isDefined(value[item.name])) {
						if (!Ext.isArray(value[item.name])) {
							value[item.name] = [value[item.name]];
						}

						if (rowNum < value[item.name].length) {
							rowNum = value[item.name].length;
						}
					}

					values[item.name.replace("[]", "")] = value[item.name];
				}
			}, this);

			var i;

			for (i = 0; i < rowNum; i++) {
				rows[i] = this.getNewRow(values, i);
			}
		} else {
			rows[0] = this.getNewRow();
		}

		return rows;
	},

	getEditRemoveRow: function() {
		var labelRow = {
			xtype: 'fieldcontainer',
			action: 'repetitiveeditremoverow',
			layout: 'hbox',

			items: [{
				xtype: 'fieldcontainer',
				layout: 'hbox',
				flex: 1,

				defaults: {
					flex: 1
				},

				items: []
			},
			{
				xtype: 'displayfield',
				flex: 0,
				width: 50
			}]
		};

		Ext.Array.each(this.rowItems, function(item, index) {
			labelRow.items[0].items[index] = {};

			labelRow.items[0].items[index].xtype = 'displayfield';
			labelRow.items[0].items[index].action = 'editremove';
			labelRow.items[0].items[index].fieldXtype = item.xtype;
			labelRow.items[0].items[index].fieldCol = index;
			this.fieldCols = index;

			if (item.hasOwnProperty('flex')) {
				labelRow.items[0].items[index].flex = item.flex;
			}

			if (item.hasOwnProperty('width')) {
				labelRow.items[0].items[index].width = item.width;
			}

			if (item.hasOwnProperty('margin')) {
				labelRow.items[0].items[index].margin = item.margin;
			}

			item.fieldLabel = '';
		}, this);

		return labelRow;
	},

	addRow: function() {
		this.down('fieldcontainer[action="repetitivefieldrows"]').add(this.getNewRow());
	},

	addRows: function(number, empty) {
		if (empty) {
			this.down('fieldcontainer[action="repetitivefieldrows"]').removeAll();
		}

		var rows = [];

		for (index = 0; index < number; index++) {
			rows[index] = this.getNewRow();
		}

		this.down('fieldcontainer[action="repetitivefieldrows"]').add(rows);
	},

	setValue: function(value) {
		this.down('fieldcontainer[action="repetitivefieldrows"]').removeAll();
		this.down('fieldcontainer[action="repetitivefieldrows"]').add(this.getRows(value));
	},

	getValue: function() {
		var values = {};

		Ext.Array.each(this.rowItems, function(item, index) {
			var fields = [];
			values[item.name] = [];

			switch (item.xtype) {
			case 'textfield':
			case 'textareafield':
			case 'combobox':
				Ext.Array.each(this.query(item.xtype + '[name="' + item.name + '"]'), function(item1, index1) {
					values[item.name].push(item1.getValue());
				});
				break;
			case 'checkboxfield':
			case 'radiofield':
				Ext.Array.each(this.query(item.xtype + '[name="' + item.name + '"]'), function(item1, index1) {
					if (item1.getValue()) {
						values[item.name].push(index1);
					}
				});
				break;
			}
		}, this);

		return values;
	}
});