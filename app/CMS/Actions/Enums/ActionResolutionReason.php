<?php

namespace App\CMS\Actions\Enums;

enum ActionResolutionReason: string
{
    case UnsupportedActionType = 'unsupported_action_type';
    case MissingReferenceId = 'missing_reference_id';
    case InvalidReferenceId = 'invalid_reference_id';
    case InvalidDestination = 'invalid_destination';
    case UnsupportedSchemaVersion = 'unsupported_schema_version';
    case EntityNotFound = 'entity_not_found';
    case EntityUnpublished = 'entity_unpublished';
    case EntityInactive = 'entity_inactive';
    case EntityArchived = 'entity_archived';
    case EntityScheduled = 'entity_scheduled';
    case ModuleDisabled = 'module_disabled';
    case RouteUnavailable = 'route_unavailable';
    case PreviewUnavailable = 'preview_unavailable';
    case UrlResolutionFailed = 'url_resolution_failed';
}
