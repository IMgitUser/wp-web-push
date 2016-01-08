if (navigator.serviceWorker) {
  navigator.serviceWorker.register(ServiceWorker.url)
  .then(function(registration) {
    console.log('Service Worker successfully registered.');

    return registration.pushManager.getSubscription()
    .then(function(subscription) {
      if (subscription) {
        return subscription;
      }

      return registration.pushManager.subscribe({ userVisibleOnly: true })
      .then(function(newSubscription) {
        return newSubscription;
      });
    });
  })
  .then(function(subscription) {
    var key = subscription.getKey ? subscription.getKey('p256dh') : '';

    var formData = new FormData();
    formData.append('action', 'webpush_register');
    formData.append('endpoint', subscription.endpoint);
    formData.append('key', key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : '');
    // formData.append('_ajax_nonce', ServiceWorker.nonce);

    fetch(ServiceWorker.register_url, {
      method: 'post',
      body: formData,
    })
    // TODO: Remove, it's used only for debugging.
    .then(function(response) {
      response.text()
      .then(function(body) {
        console.log('Server replied: ' + body);
      });
    });
  });
}