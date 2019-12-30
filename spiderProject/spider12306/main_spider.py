from spiderProject.spider12306.dbProcess import DbProcess
import requests
import time
import redis


class Spider12306:
    def __init__(self):
        self.redisCli = redis.Redis(host="192.168.11.31", port="30002")
        self._db = DbProcess()
        self.headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36"}

    def get_ticket(self, source, destination, leave_time):
        sourceCode = self.redisCli.get(source).decode('utf-8')
        destinationCode = self.redisCli.get(destination).decode('utf-8')
        if sourceCode and leave_time:
            print(sourceCode, destinationCode)
        else:
            print("Can't find in redis! Now search mysql...")
            sql = "SELECT city,abbreviation FROM city_abbreviation WHERE city = '{}' OR city = '{}'".format(source,
                                                                                                       destination)
            ret = self._db.searchDb(sql)
            print(sql)
            for i in ret:
                if i['city'] == source:
                    sourceCode = i['abbreviation']
                    self.redisCli.set(i['city'],sourceCode)
                elif i['city'] == destination:
                    destinationCode = i['abbreviation']
                    self.redisCli.set(['city'],destinationCode)
        queryUrl = "https://kyfw.12306.cn/otn/leftTicket/queryZ?leftTicketDTO.train_date={}&leftTicketDTO.from_station={}&leftTicketDTO.to_station={}&purpose_codes=ADULT".format(
            leave_time, sourceCode, destinationCode)
        print(queryUrl)
        sess = requests.session()
        sess.get(url="https://www.12306.cn/index/", headers=self.headers)
        time.sleep(3)
        ret = sess.get(url=queryUrl, headers=self.headers)
        ret.encoding = 'utf-8'
        print(ret.text)


if __name__ == '__main__':
    s1 = Spider12306()
    s1.get_ticket('北京', '广州', '2019-12-31')
