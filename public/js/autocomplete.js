function setupAutocomplete(input, dataFetcher) {
    let currentFocus;
    
    input.addEventListener("input", function(e) {
        let a, b, i, val = this.value;
        closeAllLists();
        if (!val) { return false; }
        currentFocus = -1;
        
        a = document.createElement("DIV");
        a.setAttribute("id", this.id + "autocomplete-list");
        a.setAttribute("class", "autocomplete-items");
        this.parentNode.appendChild(a);
        
        const suggestions = dataFetcher(val);
        
        suggestions.forEach(item => {
            b = document.createElement("DIV");
            const idx = item.toLowerCase().indexOf(val.toLowerCase());
            b.innerHTML = item.substr(0, idx) + "<strong>" + item.substr(idx, val.length) + "</strong>" + item.substr(idx + val.length);
            b.innerHTML += "<input type='hidden' value='" + item.replace(/'/g, "&#39;") + "'>";
            
            b.addEventListener("click", function(e) {
                input.value = this.getElementsByTagName("input")[0].value;
                closeAllLists();
                input.dispatchEvent(new Event('change'));
                input.dispatchEvent(new Event('input'));
                if (typeof focusNextField === 'function') {
                    focusNextField(input);
                }
            });
            a.appendChild(b);
        });
    });

    input.addEventListener("keydown", function(e) {
        let x = document.getElementById(this.id + "autocomplete-list");
        if (x) x = x.getElementsByTagName("div");
        if (e.keyCode == 40) { // DOWN
            currentFocus++;
            addActive(x);
        } else if (e.keyCode == 38) { // UP
            currentFocus--;
            addActive(x);
        } else if (e.keyCode == 13) { // ENTER
            if (currentFocus > -1) {
                e.preventDefault();
                if (x) x[currentFocus].click();
            }
        }
    });

    function addActive(x) {
        if (!x) return false;
        removeActive(x);
        if (currentFocus >= x.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = (x.length - 1);
        x[currentFocus].classList.add("autocomplete-active");
    }

    function removeActive(x) {
        for (let i = 0; i < x.length; i++) {
            x[i].classList.remove("autocomplete-active");
        }
    }

    function closeAllLists(elmnt) {
        const x = document.getElementsByClassName("autocomplete-items");
        for (let i = 0; i < x.length; i++) {
            if (elmnt != x[i] && elmnt != input) {
                x[i].parentNode.removeChild(x[i]);
            }
        }
    }

    document.addEventListener("click", function (e) {
        closeAllLists(e.target);
    });
}
