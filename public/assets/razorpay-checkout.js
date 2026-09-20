'use strict';
document.addEventListener('DOMContentLoaded',function(){
  var btn=document.getElementById('rzp-pay-button');
  if(!btn||typeof Razorpay==='undefined')return;
  btn.addEventListener('click',function(){
    var opts={
      key:btn.getAttribute('data-key'),
      order_id:btn.getAttribute('data-order'),
      amount:btn.getAttribute('data-amount'),
      currency:'INR',
      name:btn.getAttribute('data-name'),
      description:btn.getAttribute('data-desc'),
      prefill:{email:btn.getAttribute('data-email')},
      theme:{color:'#1a56db'}
    };
    try{
      var rzp=new Razorpay(opts);
      rzp.on('payment.failed',function(){alert('Payment failed or was cancelled. Use Check payment status; do not create another order.');});
      rzp.open();
    }catch(e){alert('Payment popup was blocked. Allow popups, then use Check payment status.');}
  });
});
