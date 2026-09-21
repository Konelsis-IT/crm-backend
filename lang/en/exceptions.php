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
        'notification_scope_not_allowed' => 'You are not allowed to notify this audience.',
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
    'report' => [
        'template_not_found' => 'Report template not found: :code',
        'subject_required' => 'The related record must be selected for this template: :kind',
        'author_not_allowed' => 'You are not allowed to write this template: :template',
        'period_required' => 'A period must be selected.',
        'invalid_period' => 'The end date cannot be before the start date.',
        'duplicate_period_report' => 'You already have a report for this period: :no',
        'review_comment_required' => 'A note is required for revision and rejection.',
        'report_locked' => 'The report cannot be changed in this status: :status',
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
];
