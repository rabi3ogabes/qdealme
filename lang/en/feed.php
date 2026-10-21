<?php

return [
    'default_title' => ':store offers',
    'errors' => [
        'not_approved' => 'This source does not have an approved licence.',
        'no_feed' => 'Only a "Retailer feed" source with an official flyer link can be pulled.',
        'no_website' => 'Add the store\'s official website first. The flyer link must be on that website.',
        'not_retailer_site' => 'The flyer link must be on the store\'s own website (:site).',
        'not_https' => 'Only secure https:// links are allowed (:url).',
        'private_address' => 'The link points to a private network address (:host).',
        'unreachable' => 'Could not reach :url.',
        'http_status' => ':url answered with HTTP :status.',
        'too_large' => 'The file at :url is empty or larger than :mb MB.',
        'unrecognised' => 'The link did not return a flyer image, a PDF or a flyer manifest.',
        'too_many_pages' => 'The flyer manifest lists more than :max pages.',
        'page_not_image' => 'A page in the manifest is not a JPG, PNG or WebP image (:url).',
        'unexpected' => 'Something went wrong while pulling this feed. The error has been logged.',
        'pdf_unsupported' => 'The store publishes a PDF, but this server cannot convert PDFs. Ask the store for page images or a flyer manifest.',
        'no_social_page' => 'Add the store\'s official Facebook page (or Instagram account) link to this source.',
        'social_not_configured' => 'Facebook and Instagram import is not set up yet. Add META_ACCESS_TOKEN (see docs/RETAILER-FEEDS.md).',
        'social_unsupported_page' => 'The official page link must be a Facebook page, or an Instagram account once Instagram is set up.',
        'social_page_not_found' => 'Meta could not find this page. Check the official page link.',
        'social_needs_approval' => 'Meta has not yet approved QDealMe\'s app to read this page (Page Public Content Access for Facebook, Advanced Access for Instagram).',
        'social_token' => 'The Meta access token is invalid or has expired. Create a new one and update META_ACCESS_TOKEN.',
        'social_unreachable' => 'Could not reach Meta\'s API. It will be tried again at the next pull.',
        'social_api' => 'Meta\'s API answered: :message',
    ],
];
