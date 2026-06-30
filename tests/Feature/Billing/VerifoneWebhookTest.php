<?php

it('acknowledges the webhook endpoint exists while the gateway is stubbed', function () {
    // VerifoneBillingProvider throws until the contract is wired; the controller
    // turns that into a 501 ack rather than a 500.
    $this->postJson('/api/webhooks/verifone', ['type' => 'subscription.activated'])
        ->assertStatus(501)
        ->assertJsonPath('reason', 'billing_not_configured');
});
