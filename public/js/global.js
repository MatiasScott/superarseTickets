document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach((row, index) => {
        row.style.animation = `fadeInUp .25s ease ${index * 0.02}s both`;
    });

    const currentPath = window.location.pathname.toLowerCase();
    const sidebarLinks = document.querySelectorAll('.sidebar-link[href]');
    const sidebarGroups = document.querySelectorAll('.sidebar-group');
    let bestLink = null;
    let bestLength = -1;

    const setGroupState = (group, isOpen) => {
        const toggle = group.querySelector('.sidebar-toggle');
        if (!toggle) return;
        group.classList.toggle('open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    const closeOtherGroups = (groupToKeepOpen) => {
        sidebarGroups.forEach((group) => {
            if (group !== groupToKeepOpen) {
                setGroupState(group, false);
            }
        });
    };

    sidebarGroups.forEach((group) => {
        setGroupState(group, false);
        const toggle = group.querySelector('.sidebar-toggle');
        if (!toggle) return;
        toggle.addEventListener('click', () => {
            const isOpen = group.classList.contains('open');
            if (isOpen) {
                setGroupState(group, false);
                return;
            }

            closeOtherGroups(group);
            setGroupState(group, true);
        });
    });

    sidebarLinks.forEach((link) => {
        const href = link.getAttribute('href');
        if (!href || href === '#') return;

        let linkPath = '';
        try {
            linkPath = new URL(href, window.location.origin).pathname.toLowerCase();
        } catch (e) {
            return;
        }

        if (linkPath === '/' || linkPath === '') return;
        if (currentPath === linkPath || currentPath.startsWith(linkPath + '/')) {
            if (linkPath.length > bestLength) {
                bestLength = linkPath.length;
                bestLink = link;
            }
        }
    });

    if (bestLink) {
        bestLink.classList.add('active');
        const parentGroup = bestLink.closest('.sidebar-group');
        if (parentGroup) {
            closeOtherGroups(parentGroup);
            setGroupState(parentGroup, true);
        }
    } else if (sidebarGroups.length > 0) {
        closeOtherGroups(sidebarGroups[0]);
        setGroupState(sidebarGroups[0], true);
    }
});
