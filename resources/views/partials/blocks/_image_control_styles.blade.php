@once
    <style>
        .block-configured-image {
            width: var(--block-image-width, auto) !important;
            height: var(--block-image-height, auto) !important;
            object-fit: var(--block-image-fit, unset) !important;
            max-width: 100%;
        }

        .block-configured-background {
            background-position: center !important;
            background-repeat: no-repeat !important;
            background-size: var(--block-background-size, auto) !important;
        }

        @media (max-width: 767px) {
            .block-configured-image {
                width: var(--block-image-mobile-width, var(--block-image-width, auto)) !important;
                height: var(--block-image-mobile-height, var(--block-image-height, auto)) !important;
                object-fit: var(--block-image-mobile-fit, var(--block-image-fit, unset)) !important;
            }

            .block-configured-background {
                background-size: var(--block-background-mobile-size, var(--block-background-size, auto)) !important;
            }
        }
    </style>
@endonce
