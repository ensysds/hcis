import './bootstrap';
import Chart from 'chart.js/auto';

window.Chart = Chart;
window.dispatchEvent(new Event('hcis:ready'));
