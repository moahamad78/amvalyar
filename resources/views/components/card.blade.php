<div class="card">

    @isset($title)

        <div class="card-header">

            <h3>

                {{ $title }}

            </h3>

        </div>

    @endisset

    <div class="card-body">

        {{ $slot }}

    </div>

</div>