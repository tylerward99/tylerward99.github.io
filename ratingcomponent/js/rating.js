const ratingOptions = document.querySelectorAll('.rating-option');

let selectedOption = null;

ratingOptions.forEach(option => {
    option.addEventListener('click', function () {
        selectedOption = option.innerHTML;
    });
});

document.querySelector('.rating-submit-btn').addEventListener('click', function () {
    
    if (selectedOption) {
        document.getElementById('selected-rating').innerHTML = selectedOption;
        document.querySelector('.rating-container').classList.add('hidden');
        document.querySelector('.rating-success-container').classList.remove('hidden');
    } else {
        document.querySelector('.submission-error').style.display = "flex";

    }
    
});