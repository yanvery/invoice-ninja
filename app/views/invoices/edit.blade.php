@extends('header')

@section('head')
	@parent

		<script type="text/javascript" src="{{ asset('js/pdf_viewer.js') }}"></script>
		<script type="text/javascript" src="{{ asset('js/compatibility.js') }}"></script>
@stop

@section('content')
	
	<p>&nbsp;</p>

	{{ Former::open($url)->method($method)->addClass('main_form')->rules(array(
		'client' => 'required',
		'product_key' => 'max:14',
	)); }}
    
    <div class="row" style="min-height:195px" onkeypress="formEnterClick(event)">
    	<div class="col-md-5" id="col_1">
			{{ Former::select('client')->addOption('', '')->fromQuery($clients, 'name', 'public_id')->data_bind("dropdown: client")
					->addGroupClass('client_select closer-row') }}

			<div class="form-group" style="margin-bottom: 8px">
				<div class="col-lg-8 col-sm-8 col-lg-offset-4 col-sm-offset-4">
					<a href="#" data-bind="click: showClientForm, text: showClientText"></a>
				</div>
			</div>

			<div data-bind="with: client">
				<div class="form-group" data-bind="visible: contacts().length > 1, foreach: contacts">
					<div class="col-lg-8 col-lg-offset-4">
						<label for="test" class="checkbox" data-bind="attr: {for: $index() + '_check'}">
							<input type="checkbox" value="1" data-bind="checked: send_invoice, attr: {id: $index() + '_check'}">
								<span data-bind="text: fullName"/>
						</label>
					</div>				
				</div>
			</div>
			{{ Former::textarea('terms')->data_bind("value: wrapped_terms, valueUpdate: 'afterkeydown'") }}			
			
		</div>
		<div class="col-md-4" id="col_2">
			<div data-bind="visible: !is_recurring()">
				{{ Former::text('invoice_number')->label('Invoice #')->data_bind("value: invoice_number, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('invoice_date')->data_bind("value: invoice_date, valueUpdate: 'afterkeydown'")->data_date_format(DEFAULT_DATE_PICKER_FORMAT) }}
				{{ Former::text('due_date')->data_bind("value: due_date, valueUpdate: 'afterkeydown'")->data_date_format(DEFAULT_DATE_PICKER_FORMAT) }}							
			</div>
			<div data-bind="visible: is_recurring">
				{{ Former::select('frequency_id')->label('How often')->options($frequencies)->data_bind("value: frequency_id") }}
				{{ Former::text('start_date')->data_bind("value: start_date, valueUpdate: 'afterkeydown'")->data_date_format(DEFAULT_DATE_PICKER_FORMAT) }}
				{{ Former::text('end_date')->data_bind("value: end_date, valueUpdate: 'afterkeydown'")->data_date_format(DEFAULT_DATE_PICKER_FORMAT) }}
			</div>
			<div data-bind="visible: invoice_status_id() < CONSTS.INVOICE_STATUS_SENT">
				{{ Former::checkbox('recurring')->text('Enable | <a href="#" rel="tooltip" data-toggle="tooltip" title="Recurring invoices are automatically sent. Use :MONTH, :QUARTER or :YEAR for dynamic dates. Basic math works as well. ie, :MONTH-1.">Learn more</a>')->data_bind("checked: is_recurring")
					->inlineHelp($invoice && $invoice->last_sent_date ? 'Last invoice sent ' . Utils::timestampToDateString($invoice->last_sent_date) : '') }}
			</div>
			@if ($invoice && $invoice->recurring_invoice_id)
				<div style="padding-top: 6px">
					Created by a {{ link_to('/invoices/'.$invoice->recurring_invoice_id, 'recurring invoice') }}
				</div>
			@endif
			
		</div>

		<div class="col-md-3" id="col_2">
			{{ Former::text('po_number')->label('PO&nbsp;number')->data_bind("value: po_number, valueUpdate: 'afterkeydown'") }}				
			{{ Former::text('discount')->data_bind("value: discount, valueUpdate: 'afterkeydown'") }}			
			{{ Former::select('currency_id')->label('Currency')->fromQuery($currencies, 'name', 'id')->data_bind("value: currency_id") }}
			
			<div class="form-group" style="margin-bottom: 8px">
				<label for="recurring" class="control-label col-lg-4 col-sm-4">Taxes</label>
				<div class="col-lg-8 col-sm-8" style="padding-top: 7px">
					<a href="#" data-bind="click: showTaxesForm">Manage taxe rates</a>
				</div>
			</div>

		</div>
	</div>

	<p>&nbsp;</p>

	{{ Former::hidden('data')->data_bind("value: ko.toJSON(model)") }}	

	<table class="table invoice-table" style="margin-bottom: 0px !important">
	    <thead>
	        <tr>
	        	<th class="hide-border"></th>
	        	<th>Item</th>
	        	<th>Description</th>
	        	<th>Unit Cost</th>
	        	<th>Quantity</th>
	        	<th data-bind="visible: tax_rates().length > 1">Tax</th>
	        	<th>Line&nbsp;Total</th>
	        	<th class="hide-border"></th>
	        </tr>
	    </thead>
	    <tbody data-bind="sortable: { data: invoice_items, afterMove: onDragged }">
	    	<tr data-bind="event: { mouseover: showActions, mouseout: hideActions }" class="sortable-row">
	        	<td style="width:20px;" class="hide-border td-icon">
	        		<i data-bind="visible: actionsVisible() &amp;&amp; $parent.invoice_items().length > 1" class="fa fa-sort"></i>
	        	</td>
	            <td style="width:120px">	            	
	            	{{ Former::text('product_key')->useDatalist(Product::getProductKeys($products), 'key')->onkeyup('onItemChange()')
	            		->raw()->data_bind("value: product_key, valueUpdate: 'afterkeydown'")->addClass('datalist') }}
	            </td>
	            <td style="width:300px">
	            	<textarea data-bind="value: wrapped_notes, valueUpdate: 'afterkeydown'" rows="1" cols="60" style="resize: none;" class="form-control word-wrap" onchange="refreshPDF()"></textarea>
	            </td>
	            <td style="width:100px">
	            	<input onkeyup="onItemChange()" data-bind="value: cost, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control" onchange="refreshPDF()"//>
	            </td>
	            <td style="width:80px">
	            	<input onkeyup="onItemChange()" data-bind="value: prettyQty, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control" onchange="refreshPDF()"//>
	            </td>
	            <td style="width:120px; vertical-align:middle" data-bind="visible: $parent.tax_rates().length > 1">
	            	<select class="form-control" style="width:100%" data-bind="value: tax, options: $parent.tax_rates, optionsText: 'displayName'"></select>
	            </td>
	        	<td style="width:100px;text-align: right;padding-top:9px !important">
	            	<span data-bind="text: total"></span>
	            </td>
	        	<td style="width:20px; cursor:pointer" class="hide-border td-icon">
	        		&nbsp;<i data-bind="click: $parent.removeItem, visible: actionsVisible() &amp;&amp; $parent.invoice_items().length > 1" class="fa fa-minus-circle" title="Remove item"/>
	        	</td>
	        </tr>
		</tbody>
		<tfoot>	        
	        <tr>
	        	<td class="hide-border"/>
	        	<td data-bind="attr: {colspan: tax_rates().length > 1 ? 3 : 2}"/>
				<td colspan="2">Subtotal</td>
				<td style="text-align: right"><span data-bind="text: subtotal"/></td>
	        </tr>
	        <tr>
	        	<td class="hide-border" data-bind="attr: {colspan: tax_rates().length > 1 ? 4 : 3}"/>
				<td colspan="2">Paid to Date</td>
				<td style="text-align: right"></td>
	        </tr>	        
	        <tr data-bind="visible: discount() > 0">
	        	<td class="hide-border" data-bind="attr: {colspan: tax_rates().length > 1 ? 4 : 3}"/>
				<td colspan="2">Discount</td>
				<td style="text-align: right"><span data-bind="text: discounted"/></td>
	        </tr>
	        <tr>
	        	<td class="hide-border" data-bind="attr: {colspan: tax_rates().length > 1 ? 4 : 3}"/>
				<td colspan="2"><b>Balance Due</b></td>
				<td style="text-align: right"><span data-bind="text: total"/></td>
	        </tr>
	    </tfoot>
	</table>

	<p>&nbsp;</p>
	<div class="form-actions">

		<div style="display:none">
			{{ Former::text('action') }}
			@if ($invoice)		
				{{ Former::text('id') }}
			@endif
		</div>


		{{ Button::normal('Download PDF', array('onclick' => 'onDownloadClick()')) }}	

		@if ($invoice)		
			{{ DropdownButton::primary('Save Invoice',
				  Navigation::links(
				    array(
				    	array('Save Invoice', "javascript:onSaveClick()"),
				     	array('Clone Invoice', "javascript:onCloneClick()"),
				     	array(Navigation::DIVIDER),
				     	array('Archive Invoice', "javascript:onArchiveClick()"),
				     	array('Delete Invoice', "javascript:onDeleteClick()"),
				    )
				  )
				, array('id'=>'actionDropDown','style'=>'text-align:left'))->split(); }}				
		@else
			{{ Button::primary_submit('Save Invoice') }}			
		@endif

		{{ Button::primary('Send Email', array('id' => 'email_button', 'onclick' => 'onEmailClick()')) }}		
	</div>
	<p>&nbsp;</p>
	
	<!-- <textarea rows="20" cols="120" id="pdfText" onkeyup="runCode()"></textarea> -->
	<!-- <iframe frameborder="1" width="100%" height="600" style="display:block;margin: 0 auto"></iframe>	-->
	<iframe id="theIFrame" frameborder="1" width="100%" height="500"></iframe>
	<canvas id="theCanvas" style="display:none;width:100%;border:solid 1px #CCCCCC;"></canvas>


	<div class="modal fade" id="clientModal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
	  <div class="modal-dialog" style="min-width:1000px">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="clientModalLabel">New Client</h4>
	      </div>

	      <div class="container" style="width: 100%">
		<div style="background-color: #F6F6F6" class="row" data-bind="with: client" onkeypress="clientModalEnterClick(event)">
			<div class="col-md-6" style="margin-left:0px;margin-right:0px" >

				{{ Former::legend('Organization') }}
				{{ Former::text('name')->data_bind("value: name, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('website')->data_bind("value: website, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('work_phone')->data_bind("value: work_phone, valueUpdate: 'afterkeydown'")->label('Phone') }}
				
				
				{{ Former::legend('Address') }}
				{{ Former::text('address1')->label('Street')->data_bind("value: address1, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('address2')->label('Apt/Floor')->data_bind("value: address2, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('city')->data_bind("value: city, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('state')->data_bind("value: state, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('postal_code')->data_bind("value: postal_code, valueUpdate: 'afterkeydown'") }}
				{{ Former::select('country_id')->addOption('','')->label('Country')->addGroupClass('country_select')
					->fromQuery($countries, 'name', 'id')->data_bind("dropdown: country_id") }}
					
			</div>
			<div class="col-md-6" style="margin-left:0px;margin-right:0px" >

				{{ Former::legend('Contacts') }}
				<div data-bind='template: { foreach: contacts,
			                            beforeRemove: hideContact,
			                            afterAdd: showContact }'>
					{{ Former::hidden('public_id')->data_bind("value: public_id, valueUpdate: 'afterkeydown'") }}
					{{ Former::text('first_name')->data_bind("value: first_name, valueUpdate: 'afterkeydown'") }}
					{{ Former::text('last_name')->data_bind("value: last_name, valueUpdate: 'afterkeydown'") }}
					{{ Former::text('email')->data_bind("value: email, valueUpdate: 'afterkeydown'") }}
					{{ Former::text('phone')->data_bind("value: phone, valueUpdate: 'afterkeydown'") }}	

					<div class="form-group">
						<div class="col-lg-8 col-lg-offset-4">
							<span data-bind="visible: $parent.contacts().length > 1">
								{{ link_to('#', 'Remove contact', array('data-bind'=>'click: $parent.removeContact')) }}
							</span>					
							<span data-bind="visible: $index() === ($parent.contacts().length - 1)" class="pull-right">
								{{ link_to('#', 'Add contact', array('data-bind'=>'click: $parent.addContact')) }}
							</span>
						</div>
					</div>
				</div>

				{{ Former::legend('Additional Info') }}
				{{ Former::select('currency_id')->addOption('','')->label('Currency')->data_bind('value: currency_id')
					->fromQuery($currencies, 'name', 'id') }}
				{{ Former::select('client_size_id')->addOption('','')->label('Size')->data_bind('value: client_size_id')
					->fromQuery($clientSizes, 'name', 'id')->select($client ? $client->client_size_id : '') }}
				{{ Former::select('client_industry_id')->addOption('','')->label('Industry')->data_bind('value: client_industry_id')
					->fromQuery($clientIndustries, 'name', 'id')->select($client ? $client->client_industry_id : '') }}
				{{ Former::textarea('notes') }}

			</div>
		</div>
		</div>

	     <div class="modal-footer" style="margin-top: 0px">
	      	<span class="error-block" id="nameError" style="display:none;float:left">Please provide a value for the name field.</span><span>&nbsp;</span>
	      	<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
	        <button type="button" class="btn btn-primary" data-bind="click: clientFormComplete">Done</button>	      	
	     </div>
	  		
	    </div>
	  </div>
	</div>

	<div class="modal fade" id="taxModal" tabindex="-1" role="dialog" aria-labelledby="taxModalLabel" aria-hidden="true">
	  <div class="modal-dialog" style="min-width:150px">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="taxModalLabel">Tax Rates</h4>
	      </div>

	      <div style="background-color: #F6F6F6" onkeypress="taxModalEnterClick(event)">
			<table class="table invoice-table sides-padded" style="margin-bottom: 0px !important">
			    <thead>
			        <tr>
			        	<th class="hide-border"></th>
			        	<th class="hide-border">Name</th>
			        	<th class="hide-border">Rate</th>
			        	<th class="hide-border"></th>
			        </tr>
			    </thead>
			    <tbody data-bind="foreach: tax_rates">
			    	<tr data-bind="event: { mouseover: showActions, mouseout: hideActions }">
			    		<td style="width:10px" class="hide-border"></td>
			            <td style="width:60px">
			            	<input onkeyup="onTaxRateChange()" data-bind="value: name, valueUpdate: 'afterkeydown'" class="form-control" onchange="refreshPDF()"//>			            	
			            </td>
			            <td style="width:60px">
			            	<input onkeyup="onTaxRateChange()" data-bind="value: prettyRate, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control" onchange="refreshPDF()"//>
			            </td>
			        	<td style="width:10px; cursor:pointer" class="hide-border td-icon">
			        		&nbsp;<i data-bind="click: $parent.removeTaxRate, visible: actionsVisible() &amp;&amp; $parent.tax_rates().length > 1" class="fa fa-minus-circle" title="Remove item"/>
			        	</td>
			        </tr>
				</tbody>
			</table>
			&nbsp;
		</div>

	     <div class="modal-footer" style="margin-top: 0px">
	      	<!-- <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button> -->
	        <button type="button" class="btn btn-primary" data-bind="click: taxFormComplete">Done</button>	      	
	     </div>
	  		
	    </div>
	  </div>
	</div>


	{{ Former::close() }}

	<script type="text/javascript">
	

	$(function() {

		$('form').change(refreshPDF);

		$('#country_id').combobox();
		$('[rel=tooltip]').tooltip();

		$('#invoice_date').datepicker({
			autoclose: true,
			todayHighlight: true
		});

		$('#due_date, #start_date, #end_date').datepicker({
			autoclose: true,
			todayHighlight: true
		});

		@if ($client && !$invoice)
			$('input[name=client]').val({{ $client->public_id }});
		@endif

		var $input = $('select#client');
		$input.combobox();
		$('.client_select input.form-control').on('change', function(e) {
			var clientId = parseInt($('input[name=client]').val(), 10);	
			if (clientId > 0) { 
				model.loadClient(clientMap[clientId]);				
			} else {
				model.client.public_id(0);  // TODO_FIX
			}
		}).trigger('change');		


		$('.country_select input.form-control').on('change', function(e) {
			var countryId = parseInt($('input[name=country_id]').val(), 10);	
			model.client.country_id(countryId);
		});		


		@if ($client)
			$('#invoice_number').focus();
		@else
			//$('[name="client_combobox"]').focus();
		@endif
		
		$('#clientModal').on('shown.bs.modal', function () {
			$('#name').focus();			
		}).on('hidden.bs.modal', function () {
			if (model.clientBackup) {
				model.loadClient(model.clientBackup);
				refreshPDF();
			}
		})
		
		$('#taxModal').on('shown.bs.modal', function () {
			$('#taxModal input:first').focus();			
		}).on('hidden.bs.modal', function () {
			if (model.taxBackup) {
				
			}
		})

		$('#actionDropDown > button:first').click(function() {
			onSaveClick();
		});

		$('label.radio').addClass('radio-inline');
		
		applyComboboxListeners();
		refreshPDF();		
	});	

	function applyComboboxListeners() {
		var value;
		$('.datalist').on('focus', function() {
			value = $(this).val();
		}).on('blur', function() {
			if (value != $(this).val()) refreshPDF();
		}).on('input', function() {			
			var key = $(this).val();
			for (var i=0; i<products.length; i++) {
				var product = products[i];
				if (product.product_key == key) {
					var model = ko.dataFor(this);
					//model.notes(product.notes);
					//model.cost(product.cost);
					//model.qty(product.qty);
					break;
				}
			}
		});
	}

	function createInvoiceModel() {
		var invoice = ko.toJS(model);
		@if (file_exists($account->getLogoPath()))
			invoice.image = "{{ HTML::image_data($account->getLogoPath()) }}";
			invoice.imageWidth = {{ $account->getLogoWidth() }};
			invoice.imageHeight = {{ $account->getLogoHeight() }};
		@endif
		return invoice;
	}

	function refreshPDF() {
		setTimeout(function() {
			_refreshPDF();
		}, 100);
	}	


	function _refreshPDF() {
		console.log("refreshPDF");
		var invoice = createInvoiceModel();
		var doc = generatePDF(invoice);		

		/*		
		var string = doc.output('dataurlstring');
		var pdfAsArray = convertDataURIToBinary(string);	
	    PDFJS.getDocument(pdfAsArray).then(function getPdfHelloWorld(pdf) {

	      pdf.getPage(1).then(function getPageHelloWorld(page) {
	        var scale = 1.5;
	        var viewport = page.getViewport(scale);

	        var canvas = document.getElementById('theCanvas');
	        var context = canvas.getContext('2d');
	        canvas.height = viewport.height;
	        canvas.width = viewport.width;

	        page.render({canvasContext: context, viewport: viewport});
	      });
	    });				
		*/

		var string = doc.output('datauristring');
		$('#theIFrame').attr('src', string);		
	}

	function onDownloadClick() {
		var invoice = createInvoiceModel();
		var doc = generatePDF(invoice);
		doc.save('Invoice-' + $('#invoice_number').val() + '.pdf');
	}

	function onEmailClick() {
		if (confirm('Are you sure you want to email this invoice?')) {
			$('#action').val('email');
			$('.main_form').submit();
		}
	}

	function onSaveClick() {
		$('.main_form').submit();
	}

	function onCloneClick() {
		$('#action').val('clone');
		$('.main_form').submit();
	}

	function onArchiveClick() {
		$('#action').val('archive');
		$('.main_form').submit();
	}

	function onDeleteClick() {
		if (confirm('Are you sure you want to delete this invoice?')) {
			$('#action').val('delete');
			$('.main_form').submit();
		}		
	}

	function formEnterClick(event) {
		if (event.keyCode === 13){
			if (event.target.type == 'textarea') {
				return;
			}

			event.preventDefault();		     				
			$('.main_form').submit();
			return false;
		}
	}

	function clientModalEnterClick(event) {		
		if (event.keyCode === 13){
			event.preventDefault();		     	
            model.clientFormComplete();
            return false;
        }
	}

	function taxModalEnterClick(event) {		
		if (event.keyCode === 13){
			event.preventDefault();		     	
            model.taxFormComplete();
            return false;
        }
	}

	function InvoiceModel() {
		var self = this;		
		this.client = new ClientModel();		
		self.discount = ko.observable('');
		self.frequency_id = ko.observable('');
		self.currency_id = ko.observable({{ Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY) }});
		self.terms = ko.observable('');		
		self.po_number = ko.observable('');
		self.invoice_date = ko.observable('');
		self.invoice_number = ko.observable('');
		self.due_date = ko.observable('');
		self.start_date = ko.observable('');
		self.end_date = ko.observable('');
		self.is_recurring = ko.observable(false);
		self.invoice_status_id = ko.observable(0);

		self.invoice_items = ko.observableArray();
		self.tax_rates = ko.observableArray();

		self.mapping = {
		    'invoice_items': {
		        create: function(options) {
		            return new ItemModel(options.data);
		        }
		    }
		}

		self.loadClient = function(client) {
			//console.log(client);				
			ko.mapping.fromJS(client, model.client.mapping, model.client);
		}

		self.wrapped_terms = ko.computed({
			read: function() {
				return this.terms();
			},
			write: function(value) {
				value = wordWrapText(value, 250);
				self.terms(value);
				$('#terms').height(value.split('\n').length * 22);
			},
			owner: this
		});

		self.showClientText = ko.computed(function() {
        	return self.client.public_id() ? 'Edit client details' : 'Create new client';
    	});


		self.showTaxesForm = function() {
			self.taxBackup = ko.mapping.toJS(self.tax_rates);

			$('#taxModal').modal('show');	
		}	

		self.taxFormComplete = function() {
			model.taxBackup = false;
			$('#taxModal').modal('hide');	
		}


		self.showClientForm = function() {
			self.clientBackup = ko.mapping.toJS(self.client);
			//console.log(self.clientBackup);

			if (self.client.public_id() == 0) {
				$('#clientModal input').val('');
				$('#clientModal #country_id').val('');
			}
			
			$('#nameError').css( "display", "none" );			
			$('#clientModal').modal('show');			
		}

		self.clientFormComplete = function() {
			var name = $('#name').val();
			if (!name) {
				if (!name) $('#nameError').css( "display", "inline" );
				return;
			}

			$('select#client').combobox('setSelected');
			if (self.client.public_id() == 0) {
				self.client.public_id(-1);
			}
			$('.client_select input.form-control').val(name);
			$('.client_select .combobox-container').addClass('combobox-selected');

			$('#nameError').css( "display", "none" );
			$('.client_select input.form-control').focus();			

			refreshPDF();
			model.clientBackup = false;
			$('#clientModal').modal('hide');			
		}

		self.removeItem = function(item) {
			self.invoice_items.remove(item);
			refreshPDF();
		}

		self.addItem = function() {
			var itemModel = new ItemModel();
			self.invoice_items.push(itemModel);	
			applyComboboxListeners();			
		}

		self.removeTaxRate = function(taxRate) {
			self.tax_rates.remove(taxRate);
			//refreshPDF();
		}

		self.addTaxRate = function(data) {
			var itemModel = new TaxRateModel(data);
			self.tax_rates.push(itemModel);	
			applyComboboxListeners();
		}

		this.rawSubtotal = ko.computed(function() {
		    var total = 0;
		    for(var p = 0; p < self.invoice_items().length; ++p)
		    {
		        total += self.invoice_items()[p].rawTotal();
		    }
		    return total;
		});

		this.subtotal = ko.computed(function() {
		    var total = self.rawSubtotal();
		    return total > 0 ? formatMoney(total, self.currency_id()) : '';
		});


		this.discounted = ko.computed(function() {
			var total = self.rawSubtotal() * (self.discount()/100);
			return formatMoney(total, self.currency_id());
		});

		this.total = ko.computed(function() {
		    var total = self.rawSubtotal();

		    var discount = parseFloat(self.discount());
		    if (discount > 0) {
		    	total = total * ((100 - discount)/100);
		    }

		    return total > 0 ? formatMoney(total, self.currency_id()) : '';
    	});

    	self.onDragged = function(item) {
    		refreshPDF();
    	}
	}

	function ClientModel(data) {
		var self = this;
		self.public_id = ko.observable(0);
		self.name = ko.observable('');
		self.work_phone = ko.observable('');
		self.notes = ko.observable('');
		self.address1 = ko.observable('');
		self.address2 = ko.observable('');
		self.city = ko.observable('');
		self.state = ko.observable('');
		self.postal_code = ko.observable('');
		self.country_id = ko.observable('');
		self.client_size_id = ko.observable('');
		self.client_industry_id = ko.observable('');
		self.currency_id = ko.observable('');
		self.website = ko.observable('');
		self.contacts = ko.observableArray();

		self.mapping = {
	    	'contacts': {
	        	create: function(options) {
	            	return new ContactModel(options.data);
	        	}
	    	}
		}

		self.showContact = function(elem) { if (elem.nodeType === 1) $(elem).hide().slideDown() }
		self.hideContact = function(elem) { if (elem.nodeType === 1) $(elem).slideUp(function() { $(elem).remove(); }) }

		self.addContact = function() {
			var contact = new ContactModel();
			console.log('num: ' + self.contacts().length);
			if (self.contacts().length == 0) {
				contact.send_invoice(true);
			}
			self.contacts.push(contact);
			return false;
		}

		self.removeContact = function() {
			self.contacts.remove(this);			
		}

		if (data) {
			ko.mapping.fromJS(data, {}, this);
		} else {
			self.addContact();
		}		
	}

	function ContactModel(data) {
		var self = this;
		self.public_id = ko.observable('');
		self.first_name = ko.observable('');
		self.last_name = ko.observable('');
		self.email = ko.observable('');
		self.phone = ko.observable('');		
		self.send_invoice = ko.observable(false);

		if (data) {
			ko.mapping.fromJS(data, {}, this);		
		}		

		self.fullName = ko.computed(function() {
			return self.first_name() + ' ' + self.last_name();
		});		
	}

	function TaxRateModel(data) {
		var self = this;
		self.public_id = ko.observable('');
		self.rate = ko.observable();
		self.name = ko.observable('');
		self.is_deleted = ko.observable(false);
		self.actionsVisible = ko.observable(false);

		if (data) {
			ko.mapping.fromJS(data, {}, this);		
		}		

		this.prettyRate = ko.computed({
	        read: function () {
	            return this.rate() ? parseFloat(this.rate()) : '';
	        },
	        write: function (value) {
	            this.rate(value);
	        },
	        owner: this
	    });				


		self.displayName = ko.computed(function() {
			var name = self.name() ? self.name() : false;
			var rate = self.rate() ? parseFloat(self.rate()) : false;
			return (name && rate) ? (rate + '%' + ' - ' + name) : '';
		});	

    	self.hideActions = function() {
			self.actionsVisible(false);
    	}

    	self.showActions = function() {
			self.actionsVisible(true);
    	}		

    	self.isEmpty = function() {
    		return !self.rate() && !self.name();
    	}    	
	}

	function ItemModel(data) {
		var self = this;		
		this.product_key = ko.observable('');
		this.notes = ko.observable('');
		this.cost = ko.observable();
		this.qty = ko.observable();
		this.tax = ko.observable();
		this.actionsVisible = ko.observable(false);
		
		this.prettyQty = ko.computed({
	        read: function () {
	            return this.qty() ? parseFloat(this.qty()) : '';
	        },
	        write: function (value) {
	            this.qty(value);
	        },
	        owner: this
	    });				

		if (data) {
			ko.mapping.fromJS(data, {}, this);			
			if (this.cost()) this.cost(formatMoney(this.cost(), model.currency_id(), true));
		}

		for (var i=0; i<model.tax_rates().length; i++) {
			var taxRate = model.tax_rates()[i];
			if (data && (data.tax_name == taxRate.name() && data.tax_rate == taxRate.rate())) {
				self.tax(taxRate);
				break;
			} else if ((!data || !data.tax_name) && !taxRate.name()) {
				self.tax(taxRate);
				break;				
			}
		}
		
		// if the tax was deleted but exists for the line item
		if (data && data.tax_name && (parseFloat(data.tax_rate)) && !self.tax()) {
			var taxRate = new TaxRateModel();
			taxRate.rate(parseFloat(data.tax_rate));
			taxRate.name(data.tax_name);
			taxRate.is_deleted(true);
			model.tax_rates.push(taxRate);
			self.tax(taxRate);
		}		

		self.wrapped_notes = ko.computed({
			read: function() {
				return this.notes();
			},
			write: function(value) {
				value = wordWrapText(value);
				self.notes(value);
				onItemChange();
			},
			owner: this
		});

		this.rawTotal = ko.computed(function() {
			var cost = parseFloat(self.cost());
			var qty = parseFloat(self.qty());
			var taxRate = self.tax() ? parseFloat(self.tax().rate()) : 0;
        	var value = cost * qty;        	
        	if (taxRate > 0) {
        		value += value * (taxRate/100);
        	}        	
        	return value ? value : '';
    	});		

		this.total = ko.computed(function() {
			var total = self.rawTotal();
			return total ? formatMoney(total, model.currency_id()) : '';
    	});

    	this.hideActions = function() {
			this.actionsVisible(false);
    	}

    	this.showActions = function() {
			this.actionsVisible(true);
    	}

    	this.isEmpty = function() {
    		return !self.product_key() && !self.notes() && !self.cost() && !self.qty();
    	}

    	this.onSelect = function(){
              console.log("select");
        }
	}

	function onItemChange()
	{
		var hasEmpty = false;
		for(var i=0; i<model.invoice_items().length; i++) {
			var item = model.invoice_items()[i];
			if (item.isEmpty()) {
				hasEmpty = true;
			}
		}

		if (!hasEmpty) {
			model.addItem();
		}

		$('.word-wrap').each(function(index, input) {
			$(input).height($(input).val().split('\n').length * 22);
		});
	}

	function onTaxRateChange()
	{
		var hasEmpty = false;
		for(var i=0; i<model.tax_rates().length; i++) {
			var taxRate = model.tax_rates()[i];
			if (taxRate.isEmpty()) {
				hasEmpty = true;
			}
		}

		if (!hasEmpty) {
			model.addTaxRate();
		}
	}

	var products = {{ $products }};
	var clients = {{ $clients }};	
	var clientMap = {};

	for (var i=0; i<clients.length; i++) {
		var client = clients[i];
		for (var j=0; j<client.contacts.length; j++) {
			var contact = client.contacts[j];
			contact.send_invoice = contact.is_primary;
		}
		clientMap[client.public_id] = client;
	}


	window.model = new InvoiceModel();
	@foreach ($taxRates as $taxRate)
		model.addTaxRate({{ $taxRate }});	
	@endforeach
	model.addTaxRate();
	@if ($invoice)
		var invoice = {{ $invoice }};
		ko.mapping.fromJS(invoice, model.mapping, model);
		if (!model.discount()) model.discount('');
		var invitationContactIds = {{ json_encode($invitationContactIds) }};		
		var client = clientMap[invoice.client.public_id];
		for (var i=0; i<client.contacts.length; i++) {
			var contact = client.contacts[i];
			contact.send_invoice = invitationContactIds.indexOf(contact.public_id) >= 0;
		}
	@else
		model.invoice_number('{{ $invoiceNumber }}');
		model.terms(wordWrapText('{{ $account->invoice_terms }}', 250));		
	@endif
	model.addItem();
	ko.applyBindings(model);

	</script>

@stop