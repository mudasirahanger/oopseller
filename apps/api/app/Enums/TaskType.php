<?php

namespace App\Enums;

enum TaskType: string
{
    case LISTING_AUDIT = 'listing_audit';
    case KEYWORD_RESEARCH = 'keyword_research';
    case LISTING_COPY = 'listing_copy';
    case IMAGE_DESIGN = 'image_design';
    case A_PLUS_CONTENT = 'a_plus_content';
    case PPC_OPTIMIZATION = 'ppc_optimization';
    case COMPETITOR_ANALYSIS = 'competitor_analysis';
    case CLIENT_APPROVAL = 'client_approval';
}
