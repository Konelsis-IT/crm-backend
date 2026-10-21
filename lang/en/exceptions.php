<?php

/*
|--------------------------------------------------------------------------
| Business exception messages
|--------------------------------------------------------------------------
|
| Keys are derived from the exception class name:
|
|   App\Exceptions\StaleRecordException                  -> stale_record
|   App\Exceptions\Personnel\EmailAlreadyInUseException  -> personnel.email_already_in_use
|
| When adding an exception, never write the message in the class; add it
| here and in lang/tr/exceptions.php.
|
*/

return [

    // Shown when a key cannot be found.
    'generic' => 'The operation could not be completed. Please try again.',

    'stale_record' => 'Someone else changed this record after you opened it. Refresh the page and try again.',
    'invalid_transition' => 'This status change is not allowed.',
    'code_already_in_use' => 'This code is already in use: :code',
    'record_not_found' => 'Record not found.',
    'model_not_resolved' => 'The model this service works on could not be resolved: :service',
    'duplicate_record' => 'This record already exists.',
    'record_archived' => 'This record is archived. Restore it from the archive before changing it.',

    'personnel' => [
        'email_already_in_use' => 'This e-mail address is already in use: :email',
        'self_parent_not_allowed' => 'A unit cannot be its own parent (directly or through the chain).',
        'circular_manager_chain' => 'A person cannot become the direct manager of someone above them in the chain.',
    ],

    'acquisition' => [
        'handoff_not_acceptable' => 'The operation handoff cannot be accepted: :reason',
        'guard_not_satisfied' => 'This step cannot be taken: :reason',
    ],

    'project' => [
        'dependency_cycle' => 'This dependency would create a cycle; not allowed.',
        'same_project_required' => 'Linked records must belong to the same project.',
        'project_component_duplicate' => 'This component is already defined for the project; it cannot be added twice.',
        'allocation_exceeded' => 'Allocation percentages cannot exceed 100 in total (currently :total).',
        // D-175
        'coordinator_not_active' => 'Only an active person can be selected as project type coordinator.',
    ],

    'actor_required' => 'A signed-in person is required for this action.',

    'approval' => [
        'no_published_policy' => 'No published approval policy for this subject: :subject',
        'active_request_exists' => 'There is already an open approval request for this record.',
        'not_an_active_approver' => 'You cannot decide this step (you are not an approver, have no delegation, or you are the requester).',
        'subject_changed' => 'The record changed while approval was running; the request was invalidated. Open a new request.',
        'comment_required' => 'A reason is required for reject and return decisions.',
        'request_not_decidable' => 'This request no longer accepts decisions (status: :status).',
        'policy_has_no_steps' => 'The policy version has no steps; add steps before publishing.',
        'approver_unresolved' => 'No approver could be found for step ":step": :reason',
        'self_delegation' => 'A person cannot delegate to themselves.',
        'version_not_draft' => 'Only a draft version can be edited (status: :status).',
    ],

    'notification' => [
        'notification_scope_not_allowed' => 'You are not allowed to send announcements to this audience.',
        'no_recipients' => 'No active recipient other than you was found in the selected audience.',
        'alert_not_open' => 'This alert is no longer open.',
    ],
    'chat' => [
        'not_a_member' => 'You are not a member of this conversation.',
        'empty_message' => 'The message is empty; add text, a link, a file or a document.',
        'conversation_locked' => 'This conversation is closed; no new messages can be sent.',
        'invalid_participant' => 'A conversation cannot be started with the selected person.',
        'document_not_shareable' => 'The document was not found or you are not allowed to share it.',
    ],
    'party' => [
        'activity_area_parent_invalid' => 'A sub-activity area can only belong to a main activity area; a main area with sub-areas cannot be moved under another area.',
        'meeting_note_proposal_mismatch' => 'Only proposals of the selected potential job can be linked to the meeting note.',
        'party_merge_not_allowed' => 'These two parties cannot be merged: choose two different parties of the same kind (organization or person); a merged party cannot be merged again and nothing can be merged into an archived party.',
    ],
    'work_request' => [
        'message_empty' => 'The reply is empty; add text, a link or a file.',
        'forward_not_allowed' => 'The request cannot be forwarded to this person or unit (the current addressee, the requester or the approver cannot be chosen).',
        'target_required' => 'The addressee of the request (personnel or department) must be selected.',
        'self_target' => 'You cannot open a request to yourself.',
        'note_required' => 'A reason is required for rejection.',
        'approver_required' => 'An approver must be selected for a request that requires approval.',
        'requester_cannot_handle' => 'You cannot accept, complete or reject your own request; those actions belong to the addressee.',
        'approver_invalid' => 'The approver cannot be the requester or the addressee.',
        'approval_policy_missing' => 'The ":policy" approval policy is not defined; run ApprovalPolicySeeder.',
    ],
    // D-189 purchasing
    // D-192 Konelsis AI connection (app/Exceptions/AiService)
    'ai_service' => [
        'ai_service_not_configured' => 'The Konelsis AI connection is not configured in this environment.',
        'ai_service_unavailable' => 'Konelsis AI is not responding right now; try again shortly.',
        'ai_service_access_denied' => 'Konelsis AI denied access for this operation; have the connection settings checked.',
        'ai_service_request_rejected' => 'Konelsis AI rejected the documents; check their type and size.',
        'ai_service_limit_reached' => 'Konelsis AI\'s daily reading limit is used up; try again tomorrow.',
        'ai_service_result_invalid' => 'Konelsis AI\'s answer could not be read; try again.',
    ],
    'procurement' => [
        'cost_list_format' => 'The cost list Excel format was not recognised. A product/service sheet starting with "Birim_Fiyat" (and the "İdari Kadro ve Genel Giderler" sheet) is required.',
        'cost_list_already_imported' => 'This cost list has already been imported into this proposal version.',
        'cost_line_not_matched' => 'The item ":line" is not matched to the catalog; pick one of the similar items or add it as a new item first.',
        'purchase_need_closed' => 'This purchase item is closed or archived and cannot be changed.',
        'project_approval_pending' => 'The project has not approved this item yet; it does not reach purchasing without project approval.',
        'purchase_approval_required' => 'This order cannot be completed without approval. Approver: :approvers.',
        'purchase_quote_required' => 'Choose a supplier quote as the recommended quote first.',
        'purchase_quote_rate_required' => 'A foreign-currency quote needs a TRY rate; the approval limit is compared in TRY.',
        'catalog_item_exists' => 'The catalog already has an item named ":name".',
        'purchase_not_allowed' => 'You are not allowed to do this.',
        'catalog_item_required' => 'This request names an item that is not in the catalog; link it to a catalog item first ("Match to catalog").',
        'purchase_request_incomplete' => 'A purchase request needs an item (from the catalog or by name) and a justification.',
        // D-230
        'purchase_project_required' => 'A purchase request for the field needs a project.',
        'purchase_request_required' => 'An in-house purchase must be linked to a purchase request; choose the request first.',
        // D-192 (B53)
        'purchase_quotation_locked' => 'This purchase quotation was ordered, rejected or cancelled; it cannot be changed.',
        'purchase_quotation_not_selected' => 'An order can only be opened from the selected purchase quotation; select it as the best offer first.',
        'purchase_quotation_empty' => 'The quotation has no priced line; enter lines and unit prices first.',
        'purchase_need_not_quotable' => '":title" cannot be added to a quotation: it awaits project approval, is already in an order or is closed.',
        'quotation_line_catalog_required' => '":line" is not matched to the Materials and Services; choose it from the list or add it as a new item first.',
        'purchase_order_closed' => 'This order is completed, cancelled or archived; it cannot be changed.',
        'purchase_order_exists' => 'This quotation has already been turned into an order.',
        'quotation_ai_no_readable_document' => 'No readable document: upload a PDF, Word (.docx), Excel (.xlsx) file or a photo (up to 10 MB each).',
        'quotation_ai_busy' => 'Konelsis AI is already reading this quotation\'s documents; try again when it finishes.',
        // D-194 (B54): Settings > Units
        'unit_exists' => 'A unit named ":name", or one of this type with the symbol ":symbol", already exists.',
        'unit_dimension_locked' => 'This unit is linked to other units by a conversion; its type cannot be changed.',
    ],
    'report' => [
        'template_not_found' => 'Report template not found: :code',
        'subject_required' => 'The related record must be selected for this template: :kind',
        'author_not_allowed' => 'You are not allowed to write this template: :template',
        'period_required' => 'A period must be selected.',
        'invalid_period' => 'The end date cannot be before the start date.',
        'duplicate_period_report' => 'You already have a report for this period: :no',
        'review_comment_required' => 'A note is required for revision and rejection.',
        'report_locked' => 'The report cannot be changed in this status: :status',
        // Work board (B36, D-115)
        'control_section_not_found' => 'Control section not found.',
        'day_already_closed' => 'This day is closed: :no. Cards added later join the report automatically.',
        'week_already_closed' => 'This week is closed: :no.',
        'waiting_source_required' => 'Choose who a waiting card is waiting on.',
        'invalid_parent' => 'A card cannot be its own parent job, nor be placed under a child card or under a card that has children.',
        'suggestion_unavailable' => 'This suggestion is no longer available; it may have become a card or been dismissed.',
        'linked_record_not_found' => 'The linked record was not found.',
        'assignee_not_allowed' => 'You must be the person\'s manager or authorised to write a card for someone else.',
        'week_summary_required' => 'Write the summary of the week to close it.',
    ],
    // Social Media (B31). Placeholders: urgent_not_allowed :days, urgent_cooldown :hours,
    // image_too_large :max (megapixels), file_too_large and body_too_large :max (readable size, e.g. "25 MB").
    'social_media' => [
        'decision_note_required' => 'A note is required when rejecting or requesting a revision.',
        'not_approved' => 'Only approved content can be marked as published.',
        'urgent_not_allowed' => 'Urgent approval can be requested only for content with a planned date that is at most :days days away.',
        'urgent_cooldown' => 'Urgent approval was requested for this content a short while ago. Wait :hours hours before asking again.',
        'unsupported_media' => 'The file was not accepted: its type is not supported, it is empty or corrupt, or the version limit for this image has been reached.',
        'image_too_large' => 'The image is too large to process. Images of up to :max megapixels can be used; try a smaller file.',
        'media_not_image' => 'This action is available for images only.',
        'upload_session_invalid' => 'The upload session is invalid or expired, or the limit of simultaneous uploads has been reached. Start the upload again.',
        'content_archived' => 'Archived content cannot be changed. Take it out of the archive first.',
        'empty_content' => 'The content is empty and cannot be approved: a photo content needs at least one image, a video content needs a video file or link, a text content needs its text.',
        'file_too_large' => 'The file is too large. Maximum allowed size: :max',
        'body_too_large' => 'The text is too long. Maximum allowed size: :max',
        'urgent_no_recipient' => 'No approver could be notified about the urgent approval. Please inform your manager directly.',
        'content_published' => 'Published content cannot be changed. Undo the publication first to edit it.',
    ],
    'platform' => [
        'invalid_release_version' => 'Version ":version" is not valid. Write it as "2.4" or "2.4.1".',
        'release_already_published' => ':version is already the published version.',
        'release_downgrade' => ':version is older than the published :current. To roll back, run the command with the --geri-al option; features that came with :current are hidden again.',
    ],
    // D-176: download all documents.
    'document' => [
        'bundle_empty' => 'There are no documents on this record that you can download.',
        'bundle_not_created' => 'The documents could not be written into a ZIP file. Please try again shortly.',
    ],
];
