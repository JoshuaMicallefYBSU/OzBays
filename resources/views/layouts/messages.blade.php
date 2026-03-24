@if(session()->has('success'))
    <div class="alert-wrapper auto-close alert-raised">
        <div class="alert alert-success">
            {{ session('success') }}
            <button type="button" class="alert-close">&times;</button>
        </div>
    </div>
@endif

@if(session()->has('error'))
    <div class="alert-wrapper auto-close alert-raised">
        <div class="alert alert-danger">
            <b>ERR.</b> {{ session('error') }}
            <button type="button" class="alert-close">&times;</button>
        </div>
    </div>
@endif

@if(session()->has('info'))
    <div class="alert-wrapper auto-close alert-raised">
        <div class="alert alert-info">
            {{ session('info') }}
            <button type="button" class="alert-close">&times;</button>
        </div>
    </div>
@endif

<style>
.alert-wrapper {
    overflow: hidden;
    transition: height 0.6s ease, opacity 0.5s ease, margin 0.6s ease;
}

.alert-raised {
    margin-top: -25px;
}

.alert-wrapper.closing {
    opacity: 0;
    margin-top: 0;
    margin-bottom: 0;
}

.alert {
    position: relative;
    padding-right: 2.5rem;
}

.alert-close {
    position: absolute;
    top: 50%;
    right: 1rem;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 1.4rem;
    line-height: 1;
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
}

.alert-close:hover {
    opacity: 1;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    function closeWrapper(wrapper) {
        if (wrapper.classList.contains('closing')) return;
            wrapper.style.height = wrapper.offsetHeight + 'px';
            wrapper.offsetHeight;
            wrapper.classList.add('closing');
            wrapper.style.height = '0px';
            setTimeout(() => wrapper.remove(), 700);
    }

    const autoCloseTimer = setTimeout(() => {
        document.querySelectorAll('.alert-wrapper.auto-close').forEach(closeWrapper);
    }, 25000);

    // Emanuel Close Activated *PARTY NOISES HEARD*
    document.querySelectorAll('.alert-close').forEach(button => {
        button.addEventListener('click', function () {
            const wrapper = this.closest('.alert-wrapper');
            closeWrapper(wrapper);
        });
    });

});
</script>
