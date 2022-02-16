import { Browser, Page } from 'playwright';
import { getBrowser, closeBrowser, getStorePage } from '../common/general';
import { buyStoreProduct } from '../common/store';
const serverAddress = "http://165.232.69.178/"
jest.setTimeout(5000000)
describe('Testing Accepted Deals', () => {
    let browser: Browser;
    let page: Page;
    beforeEach(async () => {
        browser = await getBrowser(true)
    });
    afterEach(async () => {
        await page.close();
        await closeBrowser()
    });
    it('Test Accept Deal', async () => {
        page = await getStorePage(serverAddress);
        await buyStoreProduct(page)
    })
})