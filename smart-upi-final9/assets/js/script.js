jQuery(function($){
    function isMobile(){ return /Mobi|Android/i.test(navigator.userAgent); }

    function initSupi5(){
        var box = $('.supi5-box');
        if(!box.length) return;
        var upi = box.data('upi');
        var timerDefault = parseInt(box.data('timer')||30,10);
        var orderId = (function(){
            try {
                if (typeof supi5 !== 'undefined' && supi5.orderId) return supi5.orderId;
                var u = new URL(window.location.href);
                var q = u.searchParams.get('order') || u.searchParams.get('order_id') || u.searchParams.get('order-received');
                if(q){ return parseInt(q,10); }
            } catch(e){}
            var el = document.querySelector('.woocommerce-order-overview li.order strong') || document.querySelector('.woocommerce-order-overview__order strong');
            if(el && /\d+/.test(el.textContent)){ return parseInt(el.textContent.replace(/\D/g,''),10); }
            return Date.now();
        })();

        // clear previous UI
        box.find('#supi5-qr-area').empty();
        box.find('#supi5-upi').text(upi);

        if(!isMobile()){
            // Desktop QR generation immediately
            var amount = $('.order-total .woocommerce-Price-amount bdi').first().text() || '';
            amount = amount.replace(/[^0-9.]/g,'');
            var host = (typeof supi5!=='undefined'&&supi5.siteUrl)?(new URL(supi5.siteUrl)).hostname.replace(/^www\./,''):'Merchant';
var amt = (amount||'').toString().replace(/[^0-9.]/g,'');
if(!amt || amt==='') amt = '0';
var od = 'OD'+orderId;
var uri = 'upi://pay?pa='+encodeURIComponent(upi)
        +'&pn='+encodeURIComponent(host)
        +'&tr='+encodeURIComponent(od)
        +'&tid='+encodeURIComponent(od)
        +'&am='+encodeURIComponent(amt)
        +'&cu=INR'
        +'&tn='+encodeURIComponent('Order '+orderId)
        +'&mc=';
            var $area = box.find('#supi5-qr-area').empty(); var qrUrl1='https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='+encodeURIComponent(uri); var qrUrl2='https://quickchart.io/qr?size=220&text='+encodeURIComponent(uri); var qrImg=$('<img/>',{src:qrUrl1,alt:'UPI QR',css:{width:'220px',height:'220px','border-radius':'8px'}}); qrImg.on('error',function(){ $(this).attr('src',qrUrl2); }); $area.append(qrImg);
            box.find('#supi5-qr-area').append(qrImg);
        } else {
            // Mobile: pay & copy buttons
            box.find('#supi5-payrow').remove();
            var payrow = $('<div id="supi5-payrow" style="text-align:center;margin-top:8px;"></div>');
            var payBtn = $('<a class="button supi5-primary" id="supi5-pay">Pay via UPI App</a>');
            var copyBtn = $('<button class="button" id="supi5-copy" style="margin-left:8px;">Copy UPI</button>');
            payrow.append(payBtn).append(copyBtn);
            box.find('#supi5-upi').after(payrow);

            payBtn.off('click').on('click', function(e){
                e.preventDefault();
                var amount = $('.order-total .woocommerce-Price-amount bdi').first().text() || '';
                amount = amount.replace(/[^0-9.]/g,'');
                // improved UPI URI (try to increase compatibility)
                var uri = 'upi://pay?pa='+encodeURIComponent(upi)+'&pn='+encodeURIComponent(document.title)+'&mc=&tid='+encodeURIComponent('OD'+orderId)+'&am='+encodeURIComponent(amount)+'&cu=INR&tn='+encodeURIComponent('Order '+orderId)+''+encodeURIComponent(upi)+'&pn='+encodeURIComponent(document.title)+'&tr='+encodeURIComponent(orderId)+'&am='+encodeURIComponent(amount)+'&tn=Order+'+encodeURIComponent(orderId)+'&cu=INR';
                // open deep link
                window.location.href = uri; if(typeof startCountdown==='function'){ startCountdown(); } if(typeof startCountdown==='function'){ startCountdown(); } if(typeof startCountdown==='function'){ startCountdown(); }
                startCountdown();
            });
            copyBtn.off('click').on('click', function(){ navigator.clipboard.writeText(upi).then(function(){ alert('UPI copied'); if(typeof startCountdown==='function'){ startCountdown(); }; if(typeof startCountdown==='function'){ startCountdown(); }; }); });
        }

        // start countdown
        startCountdown();

        function startCountdown(){
            $('#supi5-manual, #supi5-retry').hide();
            $('#supi5-progress').show();
            var attempts = box.data('supi5_attempts') || 0;
            var t = timerDefault;
            $('#supi5-timer').text(t);
            $('#supi5-fill').css('width','0%');
            clearInterval(box.data('supi5_timerInterval'));
            var interval = setInterval(function(){
                t--;
                $('#supi5-timer').text(t);
                var pct = Math.round(((timerDefault - t)/timerDefault)*100);
                $('#supi5-fill').css('width', pct + '%');
                if(t<=0){
                    clearInterval(interval);
                    $('#supi5-progress').hide();
                    attempts++;
                    box.data('supi5_attempts', attempts);
                    if(attempts < 2){
                        $('#supi5-retry').show();
                    } else {
                        $('#supi5-manual').show();
                    }
                }
            },1000);
            box.data('supi5_timerInterval', interval);

            // polling for same-device confirmation using REST check endpoint
            var poll = setInterval(function(){
                fetch(supi5.restUrl + 'check-order/' + orderId, {headers:{'X-WP-Nonce':supi5.nonce}})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if(data && data.status && (data.status==='processing' || data.status==='completed' || data.status==='paid')){
                        clearInterval(poll);
                        clearInterval(interval);
                        $('#supi5-progress').hide();
                        $('#supi5-retry').hide();
                        $('#supi5-manual').hide();
                        // show success
                        alert('Payment confirmed. Thank you!');
                        location.reload();
                    }
                }).catch(function(){ /* ignore */ });
            },2000);
            box.data('supi5_poll', poll);
        }

        // retry handler
        $('#supi5-retry-btn').off('click').on('click', function(e){
            e.preventDefault();
            startCountdown();
        });

        // manual submit via REST confirm (ensures proper update)
        $('#supi5-manual-submit').off('click').on('click', function(e){
            e.preventDefault();
            var file = $('#supi5-file')[0].files[0];
            var txn = $('#supi5-txn').val().trim();
            if(!file && !txn){ alert('Enter Transaction ID or upload a screenshot'); return; }
            if(file){
                var fd = new FormData();
                fd.append('action','supi5_upload');
                fd.append('file', file);
                fd.append('nonce', supi5.nonce);
                $.ajax({ url: supi5.ajaxUrl, type:'POST', data: fd, processData:false, contentType:false, success:function(res){
                    if(res && res.success && res.data && res.data.url){
                        fetch(supi5.restUrl + 'confirm', { method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':supi5.nonce}, body: JSON.stringify({ order_id: orderId, txn_id: txn, screenshot_url: res.data.url, source:'manual' }) })
                        .then(r=>r.json()).then(function(d){ if(d && d.success){ $('#supi5-manual-res').html('<div class="supi5-success">Confirmation received. Thank you.</div>'); setTimeout(function(){ location.reload(); },1200); } else { $('#supi5-manual-res').text('Could not confirm'); } });
                    } else { $('#supi5-manual-res').text('Upload failed'); }
                }, error:function(){ $('#supi5-manual-res').text('Upload error'); } });
            } else {
                fetch(supi5.restUrl + 'confirm', { method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':supi5.nonce}, body: JSON.stringify({ order_id: orderId, txn_id: txn, source:'manual' }) })
                .then(r=>r.json()).then(function(d){ if(d && d.success){ $('#supi5-manual-res').html('<div class="supi5-success">Confirmation received. Thank you.</div>'); setTimeout(function(){ location.reload(); },1200); } else { $('#supi5-manual-res').text('Could not confirm'); } });
            }
        });
    }

    $(document).ready(function(){ initSupi5(); });
    $(document.body).on('updated_checkout', function(){ initSupi5(); });
    $(document.body).on('checkout_loaded', function(){ initSupi5(); });
});