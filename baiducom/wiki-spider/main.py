import requests

class WikiSpider:
    def __init__(self,cookies):
        self.cookies=cookies
        self.sess = requests.session()
        self.sess.headers= {
            'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36',
            "Connection": "keep-alive",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3",
            "Sec-Fetch-Mode": "navigate", "Sec-Fetch-Site": "none", "Sec-Fetch-User": "?1",
            "Upgrade-Insecure-Requests": "1", "Accept-Encoding": "gzip, deflate,br",
            "Accept-Language": "zh-CN,zh;q=0.9", "Cache-Control": "max-age=0",
            }

    def test(self):
        ret = self.sess.get("http://wiki.baidu.com/pages/viewpage.action?pageId=218302795",cookies=self.cookies)
        print(ret.text)
if __name__ == '__main__':
    cookies={
            'BIDUPSID': '430EE7BE2D42B6656B7A8C631D51CD64',
            'PSTM': '1588742969',
            'BDORZ': 'B490B5EBF6F3CD402E515D22BCDA1598',
            'BAIDUID=430EE7BE2D42B665E5567EC0CAE6F9B1:SL=0:NR=10:FG': '1',
            'UUAP_P_TOKEN': 'PT-464797881926922240-iIiq8bn8Ax-uuap',
            'UUAP_P_TOKEN_OFFLINE': 'PT-465090717798473729-dl9bTc4nOZ-beta',
            'delPer': '0',
            'NSCUSERSESSID': 'ST-465111092651827200-CMymR-uuap',
            'NOAH_VERSION': '1',
            'JSESSIONID': '399D7B6C2BFA819CD0178FC2DC2802EA.wiki004',
            '_wiki.confluence': 'u1krsyV58PGrtUIx95R+5YIQNp1kXAghTJuneeOI2mlHXIkIDAPEn1NDGckfhKJ+Zd9iueAcb8w-1',
            'BSG_B_TOKEN=Mmqyjq1uP/hpy/eg2Z/GEYGAtStbC5iBQwNUA3VGdtLBvceSqOdZ5UIbtsbMRqRi6QWqb7Mf4LDyOX9ROvjM4g=': '',
            'H_PS_PSSID': '1458_21110_31254_31427_31270_31464_30823_26350_31163_31475_22160',
            'ZD_ENTRY': 'baidu',
            'WIKI_IDX_TOKEN': '189A2B0F9E4A84D5B481C9A49EC8DC7D6FE00D71AC4832BFBCBED6EA071BB7B6C684BEC6E9CAA41994D759B88E4511F4',
            'wiki_announce.ver': '1585962055897',
            'RT="z=1&dm=baidu.com&si=d6x3dypqt1t&ss=k9wawy1c&sl=0&tt=0&bcn=https%3A%2F%2Ffclog.baidu.com%2Flog%2Fweirwood%3Ftype%3Dperf&ul=kj8&hd': 'kmh"',
            'PSINO': '3',
        }
    ws = WikiSpider(cookies)
    ws.test()