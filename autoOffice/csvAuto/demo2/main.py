import pandas as pd
import sys

data = pd.read_csv('../csv_files/1.csv')
print(data)
data['总价']=None
for i,v in data.iterrows():
    data['总价'].loc[i]=v['成本']+v['利润']
print(data)
data.to_csv('../csv_files/1ret.csv',index=None)