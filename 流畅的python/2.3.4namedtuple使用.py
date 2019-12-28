from collections import namedtuple
City = namedtuple('City','name country population coordinates')
tokyo = City('Tokyo','JP',36.933,(35.68,139.70))
print(City._fields)
latlong = namedtuple('LatLong','lat long')
delhi_data=('delhi NCr','IN',21.94,latlong(28.86,21.24))
delhi = City._make(delhi_data)# 等同City(*dehi_data) 接受一个可迭代对象来生成这个实例
print(delhi)
delhi._asdict()
[print(key +":", value) for key , value in delhi._asdict().items()]#以ordereddict形式返回