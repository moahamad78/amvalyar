@once

<link
    rel="stylesheet"
    href="{{ asset('vendor/jalalidatepicker/jalalidatepicker.min.css') }}"
>

<style>
    .jalali-date-input {
        direction: ltr;
        text-align: left;
    }

    [data-jdp] {
        direction: ltr;
    }

    .jdp-container {
        z-index: 999999 !important;
    }
</style>


<script
    src="{{ asset('vendor/jalalidatepicker/jalalidatepicker.min.js') }}"
></script>


<script>
(function () {

    function initJalaliDatePicker() {

        if (
            typeof window.jalaliDatepicker === 'undefined'
        ) {

            console.error(
                'JalaliDatePicker: library not loaded'
            );

            return;
        }


        /*
         * ساده‌ترین Initialization ممکن.
         * خود کتابخانه تمام inputهای data-jdp
         * را پیدا می‌کند.
         */
        window.jalaliDatepicker.startWatch();


        document
            .querySelectorAll('.jalali-calendar-button')
            .forEach(function (button) {

                if (
                    button.dataset.jalaliBound === '1'
                ) {
                    return;
                }

                button.dataset.jalaliBound = '1';


                button.addEventListener(
                    'click',
                    function () {

                        const targetId =
                            button.getAttribute(
                                'data-target'
                            );

                        const input =
                            document.getElementById(
                                targetId
                            );

                        if (!input) {
                            return;
                        }


                        /*
                         * اگر API show موجود باشد،
                         * مستقیم از آن استفاده می‌کنیم.
                         */
                        if (
                            typeof window.jalaliDatepicker.show
                                === 'function'
                        ) {

                            window.jalaliDatepicker.show(
                                input
                            );

                            return;
                        }


                        /*
                         * Fallback
                         */
                        input.focus();

                        input.dispatchEvent(
                            new MouseEvent(
                                'click',
                                {
                                    bubbles: true,
                                    cancelable: true
                                }
                            )
                        );
                    }
                );
            });


        console.log(
            'JalaliDatePicker: initialized successfully'
        );
    }


    if (
        document.readyState === 'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initJalaliDatePicker
        );

    } else {

        initJalaliDatePicker();
    }

})();
</script>

@endonce