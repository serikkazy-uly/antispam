<?php


routes:
    resource: "routes/routes.yaml"
    type: "yaml"

index:
    path: /
    controller: App\Controller\DefaultController::index
    methods: ['GET']

spam:
    path: /is_spam
    controller: App\Controller\SpamController::isSpam
    methods: ['POST']

invalidate_cache:
    path: /invalidate_cache
    controller: App\Controller\SpamController::invalidateCache
    methods: ['POST']