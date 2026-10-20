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
        'pdf_unsupported' => 'The store publishes a PDF, but this server cannot convert PDFs. Ask the store for page images or a flyer manifest.',
    ],
];
