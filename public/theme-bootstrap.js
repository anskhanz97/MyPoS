(() => {
    const saved =
        localStorage.getItem("mypos.theme.staff:anonymous") ??
        localStorage.getItem("mypos.theme.register") ??
        localStorage.getItem("mypos.theme");
    document.documentElement.dataset.theme = saved === "dark" ? "dark" : "light";
})();
