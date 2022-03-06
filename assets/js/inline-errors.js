window.addEventListener('ajaxInvalidField', function(event, fieldElement, fieldName, errorMsg, isFirst) {
    fieldElement.closest('.form-group').classList.add('has-error')
})

document.querySelectorAll('[data-request]').forEach(function(el) {
    el.addEventListener('ajaxPromise', function(event, promise) {
        el.closest('form').querySelectorAll('.form-group.has-error').forEach(function(errorElement) {
            errorElement.classList.remove('has-error')
        })
    })
})
