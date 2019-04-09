<section>
  <p>
    {l s='Please transfer the invoice amount to our bank account. You will receive our order confirmation by email containing bank details and order number.'mod='cy_multibankwire'}
    {l s='Goods will be reserved %s days for you and we\'ll process the order immediately after receiving the payment.' sprintf=[$bankwireReservationDays]mod='cy_multibankwire'}
    {if $bankwireCustomText }
        <a data-toggle="modal" data-target="#bankwire-modal">{l s='More information'mod='cy_multibankwire'}</a>
    {/if}
  </p>

  <div class="modal fade" id="bankwire-modal" tabindex="-1" role="dialog" aria-labelledby="Bankwire information" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h2>{l s='Bankwire'mod='cy_multibankwire'}</h2>
        </div>
        <div class="modal-body">
          <p>{l s='Payment is made by transfer of the invoice amount to the following account:'mod='cy_multibankwire'}</p>
          {include file='module:cy_multibankwire/views/templates/hook/_partials/payment_infos.tpl'}
          {$bankwireCustomText nofilter}
        </div>
      </div>
    </div>
  </div>
</section>
