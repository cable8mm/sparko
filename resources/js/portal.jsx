import React from 'react';
import { createRoot } from 'react-dom/client';

const mount = document.getElementById('sparko-portal');

if (mount) {
    createRoot(mount).render(<div data-sparko-portal />);
}
