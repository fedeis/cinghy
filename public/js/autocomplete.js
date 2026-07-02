function setupAutocomplete(input, dataFetcher) {
    let currentFocus;
    let ignoreNextInput = 0;

    function updateList() {
        if (Date.now() - ignoreNextInput < 500) {
            return;
        }
        let a, b, i, val = input.value;
        closeAllLists();

        currentFocus = -1;

        a = document.createElement("DIV");
        a.setAttribute("id", input.id + "autocomplete-list");
        a.setAttribute("class", "autocomplete-items");
        input.parentNode.appendChild(a);

        const suggestions = dataFetcher(val);

        if (!suggestions || suggestions.length === 0) {
            closeAllLists();
            return;
        }

        if (suggestions.length === 1 && suggestions[0].toLowerCase() === val.toLowerCase()) {
            closeAllLists();
            return;
        }

        suggestions.forEach(item => {
            b = document.createElement("DIV");
            const idx = val ? item.toLowerCase().indexOf(val.toLowerCase()) : -1;

            if (idx > -1 && val) {
                b.innerHTML = item.substr(0, idx) + "<strong>" + item.substr(idx, val.length) + "</strong>" + item.substr(idx + val.length);
            } else {
                b.innerHTML = item;
            }

            b.innerHTML += "<input type='hidden' value='" + item.replace(/'/g, "&#39;") + "'>";

            b.addEventListener("click", function (e) {
                input.value = this.getElementsByTagName("input")[0].value;
                closeAllLists();
                ignoreNextInput = Date.now();
                input.dispatchEvent(new Event('change'));
                input.dispatchEvent(new Event('input'));
                if (typeof focusNextField === 'function') {
                    focusNextField(input);
                }
            });
            a.appendChild(b);
        });
    }

    input.addEventListener("input", updateList);
    input.addEventListener("focus", updateList);
    input.addEventListener("click", function (e) {
        if (document.getElementById(input.id + "autocomplete-list")) {
            return; // already open
        }
        updateList();
    });

    input.addEventListener("keydown", function (e) {
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
        for (let i = x.length - 1; i >= 0; i--) {
            if (elmnt != x[i] && (!elmnt || elmnt.id + "autocomplete-list" !== x[i].id)) {
                x[i].parentNode.removeChild(x[i]);
            }
        }
    }

    document.addEventListener("click", function (e) {
        closeAllLists(e.target);
    });
}
