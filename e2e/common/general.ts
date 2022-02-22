import { chromium, Browser, Page } from "playwright";
let browser: Browser;
let prefixTestName = 'none';
export const getBrowser = async (doLanuch: boolean) => {
    if (!browser || !doLanuch)
        browser = await chromium.launch();
    return browser;
}
export const closeBrowser = async () => browser.close();

export const getStorePage = async (storeAddress: string) => {
    let page: Page = await browser.newPage();

    // page.route('**', (route, request) => {
    //     console.log(request.url());
    //     route.continue();
    // });
    await page.goto(storeAddress);
    return page;
}

export const setTestPrefix = (name: string) => prefixTestName = name;
export const getScreenShotPath = (fileName: string) => (`./e2e/screenshots/${prefixTestName}-${fileName}.png`)

export async function scrollOnElement(page: Page, selector: string) {
    await page.$eval(selector, (element) => {
        element.scrollIntoView();
    });
}