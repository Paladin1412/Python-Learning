import pandas as pd
import sys

data = pd.read_csv('../practise1/raw_files/1.csv')
new = {"总价":[]}
npd=pd.DataFrame(new,index=[])
print(npd)
data['总价']=None
for i,v in data.iterrows():
    npd['总价'].loc[i]=v['成本']+v['利润']
npd.to_csv('../csv_files/1ret.csv',index=None)