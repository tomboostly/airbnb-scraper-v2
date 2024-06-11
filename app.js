import express from 'express';
import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import chromium from '@sparticuz/chromium';

//new
import * as dotenv from "dotenv";
dotenv.config({ path: '.env' });


puppeteer.use(StealthPlugin());

const app = express();
const PORT = process.env.PORT || 3000;


const scrollPage = async (page, reviewsLimit) => {
    const multiplier = 1;
    const getHeight = async () =>
      await page.evaluate(
        `document.querySelector('[data-testid="modal-container"] [role="dialog"] > div:last-child').scrollHeight`
      );
    const getResultsLength = async () => Array.from(await page.$$('[data-testid="modal-container"] .r1are2x1'))?.length;
    let lastHeight = await getHeight();
    let resultsLength = await getResultsLength();
    const clickCount = reviewsLimit ? 5 : 15;
    for (let i = 0; i < 3; i++) {
      await page.waitForTimeout(500 * multiplier);
      await page.keyboard.press("Tab");
    }
    while (reviewsLimit ? resultsLength < reviewsLimit + 10 : true) {
      for (let i = 0; i < clickCount; i++) {
        await page.waitForTimeout(500 * multiplier);
        await page.keyboard.press("PageDown");
      }
      await page.waitForTimeout(5000 * multiplier);
      let newHeight = await getHeight();
      if (newHeight === lastHeight) {
        break;
      }
      lastHeight = newHeight;
      resultsLength = await getResultsLength();
    }
};


const getAirbnbHotelInfo = async (link, currency = 'USD', reviewsLimit = 10) => {  

    const multiplier = 1;

    const executablePath = await chromium.executablePath();
    const args = await chromium.args;
    const defaultViewport = await chromium.defaultViewport;
    const headless = await chromium.headless;
    const currency_endpoint = "?currency=" + currency;

    console.log(link + currency_endpoint);

    const browser = await puppeteer.launch({
      // executablePath: executablePath,
      executablePath: process.env.IS_LOCAL
      ? "C:\\Users\\User\\AppData\\Local\\Chromium\\Application\\chrome.exe"
      : await executablePath,      
      // args: args,
      args: process.env.IS_LOCAL ? puppeteer.defaultArgs() : args,
      defaultViewport: defaultViewport,
      headless: headless
    });    


    const page = await browser.newPage();
   
    // Timeout
    await page.setDefaultNavigationTimeout(120000);


    // await page.goto(link + "?currency=USD", { waitUntil: 'domcontentloaded' }); 
    await page.goto(link + currency_endpoint, { waitUntil: 'networkidle0' }); 

    await page.waitForTimeout(5000 * multiplier);   
    
    // Wait for the title element to load
    await page.waitForSelector('[data-section-id="TITLE_DEFAULT"] h1');

    // Extract the title
    const title = await page.evaluate(() => {
        return document.querySelector('[data-section-id="TITLE_DEFAULT"] h1')?.textContent.trim();
    });       

    const info = await page.evaluate(() => {
      const olElement = document.querySelector('[data-section-id="OVERVIEW_DEFAULT_V2"] ol');
      const listItems = olElement ? Array.from(olElement.querySelectorAll('li')) : [];
      
      let guests, bedrooms, baths;
      
      listItems.forEach((item) => {
          if (item?.textContent.includes('guests')) guests = item?.textContent.trim();
          else if (item?.textContent.includes('bedrooms')) bedrooms = item?.textContent.trim();
          else if (item?.textContent.includes('baths')) baths = item?.textContent.trim();
      });
  
      return { guests, bedrooms, baths };
    });
  

    const room_type = await page.evaluate(() => {
        return document.querySelector('[data-section-id="OVERVIEW_DEFAULT_V2"] h2')?.textContent.trim();
    });    

    const title_split = room_type.split(' ');

    const roomType = title_split.slice(0, 2).join(' ');

    const stars = await page.evaluate(() => {
        return document.querySelector('[data-testid="pdp-reviews-highlight-banner-host-rating"]')?.textContent?.trim() || "Element not found";
    });


    await page.waitForSelector('[data-section-id="BOOK_IT_SIDEBAR"]');
    await page.click('[data-section-id="BOOK_IT_SIDEBAR"] ._16l1qv1');
    await page.waitForTimeout(3000 * multiplier);

    let keepRunning = true;

    function stopLoop() {
        keepRunning = false;
    }



  // //first availabile day test
  // await page.click('[data-section-id="AVAILABILITY_CALENDAR_INLINE"] td[aria-disabled="false"]');
  // await page.waitForTimeout(2000 * multiplier);    
  // const ariaLabel = await page.evaluate(() => {
  //   const el = document.querySelector('[data-section-id="AVAILABILITY_CALENDAR_INLINE"] td[aria-disabled="false"]');
  //   return el ? el.getAttribute('aria-label') : null;
  // });
  // console.log("######", ariaLabel);   
  
  
    
  let calendar_total = [];

  let minimum_days_found = false;
  let selectedCheckinDates = null;
  let minimum_lastNumber = null;
  
  let loopCount = 0;
  while (loopCount < 12) {
    await page.click('._qz9x4fc');
    await page.waitForTimeout(700 * multiplier);
    // Extract availability information
    const calendar = await page.evaluate(() => {
      const dayElements = document.querySelectorAll('#site-content div._14676s3 .notranslate');

      const availability = [];
      dayElements.forEach(day => {
          const date = day.getAttribute('data-testid').split('-')[2];
          const available = day.getAttribute('data-is-day-blocked') === "false" ? true : false;

          availability.push({ date, available });
      });

      return availability;
    });      

    const elementExists = await page.evaluate(() => {
      const element = document.querySelector('[data-section-id="AVAILABILITY_CALENDAR_INLINE"] td[aria-disabled="false"]');
      return !!element; // Convert element to boolean (true if element exists, false otherwise)
    });
    
    if(elementExists && !minimum_days_found){
      await page.click('[data-section-id="AVAILABILITY_CALENDAR_INLINE"] td[aria-disabled="false"]');
      await page.waitForTimeout(2000 * multiplier);     
    
      selectedCheckinDates = await page.evaluate(() => {
        const tds = Array.from(document.querySelectorAll('td[aria-disabled="true"]'));
        const selectedDates = tds
          .filter(td => td.getAttribute('aria-label').includes('Selected check-in date'))
          .map(td => td.getAttribute('aria-label'));
        return selectedDates[0];
      });
    
      let minimum_matches = null;
      if(selectedCheckinDates){
        minimum_matches = selectedCheckinDates.match(/\d+/g);
        minimum_lastNumber = minimum_matches ? minimum_matches[minimum_matches.length - 1] : null;  
      }
    }

    // Create a set to track dates that already exist
    const existingDates = new Set(calendar_total.map(item => item.date));

    // Merge arrays without duplicates
    calendar.forEach(item => {
      if (!existingDates.has(item.date)) {
        calendar_total.push(item);
        existingDates.add(item.date); // Update the set with the new date
      }
    });
    
    loopCount++;
  }        



  // console.log("!!!!" , minimum_lastNumber);  


  // await page.screenshot({ path: 'example.png' });  

  // Click the button with the value "Clear dates"
  await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll('button'));
    const clearDatesButton = buttons.find(button => button.textContent === 'Clear dates');
    if (clearDatesButton) {
      clearDatesButton.click();
    } else {
      console.error('Button with text "Clear dates" not found.');
    }
  });

  await page.reload({ waitUntil: "networkidle0" }); // Reloads the page, waiting until there are no more network connections
  await page.waitForTimeout(3000); // Waits for 2 seconds (5000 milliseconds)  
  
  // let minimum_stay = 3;

  let minimum_stay = minimum_lastNumber ? parseInt(minimum_lastNumber) + 1 : 3;

  console.log("Minimum", minimum_stay);
  
  // The while loop with a counter to limit to 15 iterations
  let counter = 0;
  const maxIterations = 15;
  
  while (keepRunning && counter < maxIterations) {
    console.log("Loop is running...");
  
    // page.on('console', msg => console.log('PAGE LOG:', msg.text()));
  
    // const length = await page.evaluate(() => {
    //   const elements = document.querySelectorAll('[data-section-id="AVAILABILITY_CALENDAR_INLINE"] td[aria-disabled="false"]');
    //   return elements.length;
    // });
  
    // console.log("Element!", length);
  
    const consecutiveDatesIndices = await page.evaluate(async (minimum_stay) => {
      const elements = document.querySelectorAll('[data-section-id="AVAILABILITY_CALENDAR_INLINE"] td[aria-disabled="false"]');
  
      let consecutiveCount = 0;
      let firstIndex = -1;
  
      for (let i = 0; i < elements.length; i++) {
        // const hasNuyjriaClass = elements[i].querySelector('div._nuyjria') !== null;
        const hasNuyjriaClass = elements[i].getAttribute('aria-label').includes('check-in');
        // console.log(window.getComputedStyle(elements[i]));
        if (hasNuyjriaClass) {
          consecutiveCount++;
          if (consecutiveCount === 1) {
            firstIndex = i;
          }
          if (consecutiveCount === minimum_stay) {
            return { start: firstIndex, end: i, ffff: elements[firstIndex].querySelector('div').getAttribute('data-testid'), eeee: elements[i].querySelector('div').getAttribute('data-testid') }; // Return indices of the first and last elements in the sequence
          }
        } else {
          consecutiveCount = 0;
          firstIndex = -1;
        }
      }
      return null; // Return null if no consecutive dates are found
    }, minimum_stay);
  
    if (consecutiveDatesIndices) {
      await page.waitForTimeout(2000 * multiplier);
  
      const firstDateSelector = `[data-section-id="AVAILABILITY_CALENDAR_INLINE"] [data-testid="${consecutiveDatesIndices.ffff}"]`;
      const secondDateSelector = `[data-section-id="AVAILABILITY_CALENDAR_INLINE"] [data-testid="${consecutiveDatesIndices.eeee}"]`;
  
      await page.evaluate(selector => {
        const childElement = document.querySelector(selector);
        const parentTd = childElement ? childElement.closest('td') : null;
        if (parentTd) {
          parentTd.click();
        }
      }, firstDateSelector);
  
      await page.waitForTimeout(2000 * multiplier);
  
      await page.evaluate(selector => {
        const childElement = document.querySelector(selector);
        const parentTd = childElement ? childElement.closest('td') : null;
        if (parentTd) {
          parentTd.click();
        }
      }, secondDateSelector);
  
      await page.screenshot({ path: 'debug.png' });
  
      console.log("((((((((1", firstDateSelector);
      console.log("((((((((2", secondDateSelector);
      stopLoop();
    } else {
      await page.click('[data-section-id="AVAILABILITY_CALENDAR_INLINE"] button[aria-label="Move forward to switch to the next month."]');
      console.log("Nothing found!!!", consecutiveDatesIndices);
    }
  
    await new Promise(resolve => setTimeout(resolve, 2000));
  
    counter++;
  }

  
  let prices = {
    amount: 0
  };  
  
  const priceString_ = await page.evaluate(() => {

    // data-testid="book-it-default"
    // const elements = document.querySelectorAll('[data-section-id="BOOK_IT_SIDEBAR"] ._1k1ce2w, [data-section-id="BOOK_IT_SIDEBAR"] ._1y74zjx, [data-section-id="BOOK_IT_SIDEBAR"] ._19y8o0j');
    const elements = document.querySelectorAll('[data-testid="book-it-default"] div:nth-child(1)');
    return elements.length > 0 ? elements[0].textContent : null;
  });

  if (priceString_) {
    try {
      prices = await page.evaluate((priceString) => {
        const matches = priceString.match(/[\d,]+(?:\.\d+)?/);
        return {
          amount: matches ? parseFloat(matches[0].replace(",", "")) : 0
        };
      }, priceString_);
    } catch (error) {
      console.log(error);
    }
  }

  // Scrape the Cleaning Fee
  const cleaningFee_ = await page.evaluate(() => {
    const feeElements = Array.from(document.querySelectorAll('div._tr4owt'));
    const cleaningFeeElement = feeElements.find(element => 
      element.querySelector('div.l1x1206l').innerText.includes('Cleaning fee')
    );
    return cleaningFeeElement ? cleaningFeeElement.querySelector('span._1k4xcdh').textContent.trim() : null;
  });

  // Scrape the Airbnb service fee
  const serviceFee_ = await page.evaluate(() => {
    const feeElements = Array.from(document.querySelectorAll('div._tr4owt'));
    const serviceFeeElement = feeElements.find(element => 
      element.querySelector('div.l1x1206l').innerText.includes('Airbnb service fee')
    );
    return serviceFeeElement ? serviceFeeElement.querySelector('span._1k4xcdh').textContent.trim() : null;
  });  

  if(cleaningFee_){
    try {
      prices['cleaning_fee'] = await page.evaluate((cleaningFee) => {
        const matches = cleaningFee.match(/[\d,]+(?:\.\d+)?/);
        return matches ? parseFloat(matches[0].replace(",", "")) : 0

      }, cleaningFee_);
    } catch (error) {
      console.log(error);
    }
  }

  if(serviceFee_){
    try {
      prices['airbnb_fee'] = await page.evaluate((serviceFee) => {
        const matches = serviceFee.match(/[\d,]+(?:\.\d+)?/);
        return matches ? parseFloat(matches[0].replace(",", "")) : 0

      }, serviceFee_);
    } catch (error) {
      console.log(error);
    }
  }  

  console.log("Prices", prices, );  
    

    // const address = await page.$eval('[data-section-id="LOCATION_DEFAULT"] section div:nth-child(2)', el => el ? el.textContent.trim() : '', { fallback: '' });
    
    let address = '';
    try {
        address = await page.$eval('[data-section-id="LOCATION_DEFAULT"] section div:nth-child(2)', el => el.textContent.trim());
    } catch (e) {
        console.log("Error occurred while fetching the address: ", e.message);
        address = '';
    }

    const shortDescription = await page.evaluate(() => {
      return document.querySelector('[data-section-id="DESCRIPTION_DEFAULT"]')?.textContent.trim() || "";
    });

    let description = "";

    if (await page.$('[data-section-id="DESCRIPTION_DEFAULT"] button') !== null) {
        await page.click('[data-section-id="DESCRIPTION_DEFAULT"] button');
        await page.waitForTimeout(3000 * multiplier);
    
        description = await page.evaluate(() => {
          return document.querySelector('[data-section-id="DESCRIPTION_MODAL"]')?.innerHTML || "";
        });
    
        await page.click('[data-testid="modal-container"] [aria-label="Close"]');
        await page.waitForTimeout(1000 * multiplier);
    }
    
    if (await page.$('[data-section-id="DESCRIPTION_LUXE"]') !== null) {
      await page.click('[data-section-id="DESCRIPTION_LUXE"] button');
      await page.waitForTimeout(3000 * multiplier);
  
      description = await page.evaluate(() => {
        return document.querySelector('[data-section-id="DESCRIPTION_MODAL"]')?.innerHTML || "";
      });
  
      await page.click('[data-testid="modal-container"] [aria-label="Close"]');
      await page.waitForTimeout(1000 * multiplier);
    }
      

    // Wait for an anchor tag containing "maps" in the href attribute
    await page.waitForSelector('a[href*=maps]', { timeout: 30000 });

    const textContent = await page.evaluate(() => document.body.textContent);

    const p_lat = /"lat":([-0-9.]+)/;
    const p_lng = /"lng":([-0-9.]+)/;
  
    const lat = textContent.match(p_lat)[1];
    const lng = textContent.match(p_lng)[1];          



    //amenities
    await page.click('[data-section-id="AMENITIES_DEFAULT"] button');
    await page.waitForTimeout(3000 * multiplier);
    const amenities = await page.evaluate(() =>
        Array.from(document.querySelectorAll('[role="dialog"] .dir-ltr[id]'))
        .map((el) => (el.id.includes("row-title") ? el.textContent.trim() : null))
        .filter((el) => el)
    );
    // console.log("Amenities", amenities) 
    if(amenities && amenities.length > 0){
      await page.click('[aria-label="Close"]');    
    }
    
    await page.waitForTimeout(1000 * multiplier); 
    const thingsToKnowButtons = Array.from(await page.$$('[data-section-id="POLICIES_DEFAULT"] button'));

    let rules = [];
    
    if (thingsToKnowButtons[0]) {
      try {
        await thingsToKnowButtons[0].click();
        await page.waitForTimeout(3000 * multiplier);
        rules = await page.evaluate(() =>
          Array.from(document.querySelectorAll('[data-testid="modal-container"] .t1rc5p4c')).map((el) => el.textContent.trim())
        );
        await page.click('[data-testid="modal-container"] [aria-label="Close"]');
        await page.waitForTimeout(1000 * multiplier);
      } catch (error) {
        console.log("Error clicking the button or extracting rules: ", error);
        // Continue execution even if there is an error
      }
    }
    // Wait for the main container to load
    await page.waitForSelector('#site-content div._14676s3');


    //place reviews
    const isReviews = await page.$('[data-section-id="REVIEWS_DEFAULT"] button');
    let all_reviews = {};
    if (isReviews) {
      try {
        await page.click('[data-section-id="REVIEWS_DEFAULT"] button');
        await page.waitForTimeout(3000 * multiplier);
        await scrollPage(page);
        all_reviews= await page.evaluate(() => {
            return {
            reviews: Array.from(document.querySelectorAll('[data-testid="modal-container"] .r1are2x1')).map((el) => ({
                name: el.querySelector("h3")?.textContent.trim(),
                avatar: el.querySelector("a img")?.getAttribute("data-original-uri"),
                userPage: `https://www.airbnb.com${el.querySelector("a")?.getAttribute("href")}`,
                // date: el.querySelector("li").textContent.trim(),
                review: el.querySelector("span")?.textContent.trim(),
                text: el.querySelector(".lrl13de")?.textContent.trim()
            })),
            };
        });
        all_reviews = all_reviews.reviews.filter((el, i) => i < 20);
        await page.click('[data-testid="modal-container"] [aria-label="Close"]');
        await page.waitForTimeout(1000 * multiplier);
      } catch (error) {
        console.log("Error clicking the button or extracting rules: ", error);
        // Continue execution even if there is an error
      }        
    }  

    await page.waitForSelector('[data-section-id="HERO_DEFAULT"] button', { visible: true });
    await page.click('[data-section-id="HERO_DEFAULT"] button');
    await page.waitForSelector('[data-testid="photo-viewer-section"]', {visible: true});
    await scrollPage(page);
  
    // await page.waitForSelector('[data-testid="modal-container"]', {visible: true});
    const all_photos = await page.evaluate(() =>
      Array.from(document.querySelectorAll('[data-testid="modal-container"] img')).map((el) => el.getAttribute("data-original-uri"))
    );      
          

    await browser.close();


    return {
        title: title,
        guests: info["guests"] ?? '',
        roomType: roomType,
        stars: stars,
        shortDescription: shortDescription,
        description: description,
        address: address,
        price: prices,
        amenities: amenities,
        coordinates: {
            lat: lat,
            lng: lng
        },
        rules: rules,
        calendar: calendar_total,
        bedrooms: info["bedrooms"] ?? '',
        baths: info["baths"] ?? '',            
        reviews: all_reviews,
        photos: all_photos,
        info: info
    };
}

app.get('/test', async (req, res) => {
  res.json({
    "success": 'success'
  });
});

const getAirbnbHotelAvailability = async (link, currency = 'USD', reviewsLimit = 10) => {  

  const multiplier = 1;

  const executablePath = await chromium.executablePath();
  const args = await chromium.args;
  const defaultViewport = await chromium.defaultViewport;
  const headless = await chromium.headless;
  const currency_endpoint = "?currency=" + currency;

  const browser = await puppeteer.launch({
    // executablePath: executablePath,
    executablePath: process.env.IS_LOCAL
    ? "C:\\Users\\User\\AppData\\Local\\Chromium\\Application\\chrome.exe"
    : await executablePath,      
    // args: args,
    args: process.env.IS_LOCAL ? puppeteer.defaultArgs() : args,
    defaultViewport: defaultViewport,
    headless: process.env.IS_LOCAL ? false : headless
  });    

  const page = await browser.newPage();
   
  // Timeout
  await page.setDefaultNavigationTimeout(120000);

  await page.goto(link + currency_endpoint, { waitUntil: 'domcontentloaded' });   

  await page.waitForTimeout(5000 * multiplier);   


  // Wait for the main container to load
  await page.waitForSelector('#site-content div._14676s3');

  let calendar_total = [];

  let loopCount = 0;
  while (loopCount < 12) {
    await page.click('._qz9x4fc');
    await page.waitForTimeout(500 * multiplier);
    // Extract availability information
    const calendar = await page.evaluate(() => {
      const dayElements = document.querySelectorAll('#site-content [data-testid="inline-availability-calendar"] [data-testid^="calendar-day-"]');
    
      const availability = [];
      dayElements.forEach(day => {
        const date = day.getAttribute('data-testid').split('-')[2];
        const isBlockedAttr = day.getAttribute('data-is-day-blocked');
        const isBlocked = isBlockedAttr === "true";
        const available = !isBlocked;
    
        // Debugging output for each date
        console.log(`Date: ${date}, isBlocked: ${isBlockedAttr}, available: ${available}`);
    
        availability.push({ date, available });
      });
    
      return availability;
    });   

    const existingDates = new Set(calendar_total.map(item => item.date));

    // Merge arrays without duplicates
    calendar.forEach(item => {
      if (!existingDates.has(item.date)) {
        calendar_total.push(item);
        existingDates.add(item.date); // Update the set with the new date
      }
    });
    
    loopCount++;
  }      


  await browser.close();

  return {
      calendar: calendar_total
  };  
}


app.get('/boostly_airbnb_scrape_availability/:airbnb_link', async (req, res) => {

  try {

      const link = "https://www.airbnb.com/rooms/"+ req.params.airbnb_link;

      getAirbnbHotelAvailability(link).then(
        (data) => {
            res.json(data);
        }
      )
      .catch(err=>{
          console.log("EEEERRR", err)
          res.send(err);
      })     
  } catch (error) {

      await browser.close();

      res.json({
          "error": error
      });
  }
});


app.get('/boostly_airbnb_scraper/:airbnb_link', async (req, res) => {

    try {
        const link = "https://www.airbnb.com/rooms/"+ req.params.airbnb_link;
        const currency = req.query.currency;

        getAirbnbHotelInfo(link, currency).then(
            (data) => {
                res.json(data);
            }
        )
        .catch(err=>{
            console.log("EEEERRR", err)
            res.send(err);
        })   
    } catch (error) {

        await browser.close();

        res.json({
            "error": error,
        });
    }
});

app.listen(PORT, () => {
  console.log(`Server is running on port ${PORT}`);
});
