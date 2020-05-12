import requests
import json
from flask import Flask,Request
from flask_restful import Api,Resource

app = Flask(__name__)
api=Api(app)


class get_all_status(Resource):
    def get(self):
        base_url = 'http://127.0.0.1/fy/'
        city_lists = []
        cities = json.loads(requests.get(base_url + "citylist").text)
        for city in cities['data']:
            c = {}
            c['city_name'] = city['city_name']
            status = json.loads(requests.get(base_url + "/current?city=" + city['city_py']).text)
            c['record'] = status['data']
            city_lists.append(c)
        return {"code":200,"data":city_lists}

api.add_resource(get_all_status,"/api/getallstatus")
if __name__ == '__main__':
    app.run()